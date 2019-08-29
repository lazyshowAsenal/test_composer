<?php
namespace Feng\Utils\Request;

use Feng\Utils\Request\Exceptions\FengRequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise;
use Log;

/**
 * 封装 curl 请求
 * 支持 get, post, put, delete 请求方法
 */
class Request
{
    protected $client;

    protected $targetIncreaseRpcId = 1;

    protected $traceId = 0; //未获取到traceId,默认值为0;应该是该模块未透传;

    protected $asyncTasks = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->traceId = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : 0;
    }

    public function get($url, array $params = [], $options = [])
    {
        return $this->request('GET', $url, $params, $options);
    }

    public function post($url, array $params = [], $options = [])
    {
        return $this->request('POST', $url, $params, $options);
    }

    public function put($url, array $params = [], $options = [])
    {
        return $this->request('PUT', $url, $params, $options);
    }

    public function delete($url, array $params = [], $options = [])
    {
        return $this->request('delete', $url, $params, $options);
    }

    protected function buildHeader(array $options)
    {
        $options['headers']['Accept'] = 'application/json';
        $options['headers']['X-REQUEST-ID'] = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : 0;
        $options['headers']['X-REQUEST-RPCID'] = $this->getTargetRpcId();
        $options['headers']['X-USERID'] = $this->getUserId();
        $options['headers']['FROM'] = get_from();
        $options['headers']['X_FORWARDED_FOR'] = get_client_ip();

        return $options;
    }

    /**
     * @return string 获取目标rpcId
     */
    protected function getTargetRpcId()
    {
        if($this->traceId === 0) return 0;
        $rpcId = isset($_SERVER['HTTP_X_REQUEST_RPCID']) ? $_SERVER['HTTP_X_REQUEST_RPCID'] : 0;
        //适用于脚本调用,超过100条以上的请求时,重置一下targetIncreaseRpcId;防止类似queue的接口调用,targetIncreaseRpcId值达到50万以上的.规避隐患.
        if(strlen($this->traceId) == 10 && $this->targetIncreaseRpcId >100) {
            $this->targetIncreaseRpcId = 1;
        }
        return $rpcId . "." . $this->targetIncreaseRpcId++ ;
    }

    /**
     * @return int 获取用户Id
     */
    protected function getUserId()
    {
        $userId = isset($_SERVER['HTTP_X_USERID']) ? $_SERVER['HTTP_X_USERID'] : 0;
        return $userId ;
    }

    public function request($method, $url, array $params, array $options = [])
    {
        if (strtoupper($method) === 'GET') {
            $qs = 'query';
        } else {
            $qs = 'json';
        }
        $params = array_merge(['from' => get_from()], $params);
        $params = array_merge($_REQUEST['operation_info'] ?? [], $params);

        if (array_has($options, 'multipart')) {
            foreach ($params as $field => $value) {
                $options['multipart'][] = [
                    'name' => $field,
                    'contents' => $value,
                ];
            }
        } elseif (array_has($options,'form_params')) {
            $options =['form_params'=>$params];
        } else {
            $options = array_merge($options, [$qs => $params]);
        }
        $options = $this->buildHeader($options);

        try {
            $begin = microtime(true);
            $res = $this->client->request($method, $url, $options);
            $elapsed = $this->getElapsed($begin);
            $this->logger('info', $res, $url, $elapsed, $options);
            $contents = $res->getBody()->getContents();
            return json_decode($contents, true);
        } catch (ConnectException $e) {
            Log::error($e->getMessage() . ' url ' . $url . ' options '. json_encode($options));
            return [
                'code' => 1,
                'msg' => $e->getMessage(),
                'error' => '请求连接出错'
            ];
        } catch (RequestException $e) {
            $elapsed = $this->getElapsed($begin);
            $response = $e->getResponse();
            // 处理 url 为空的情况
            if (is_null($response)) {
                return [
                    'code' => 1,
                    'msg' =>$e->getMessage(),
                ];
            }
            $body = $response->getBody();
            $this->logger('error', $response, $url, $elapsed, $options);

            return [
                'code' => $e->getCode(),
                'msg' => $body->getContents(),
                'error' => $e->getMessage()
            ];
        }
    }

    protected function logger($level, $response, $url, $time, $options = [], $async = false)
    {
        $message = 'target:' . $url . ' ' . $response->getStatusCode() . ' time:' . $time
            . ' options:' . json_encode($options);
        if ($async) {
            $message = 'async ' . $message;
        }
        Log::log($level, $message);
    }

    public function addAsync($method, $name, $url, array $params = [], array $options = [])
    {
        if (count($this->asyncTasks) >= 100) {
            throw new FengRequestException('最大并发请求数不能超过100个');
        }
        $method = strtolower($method);
        if (! in_array($method, ['get', 'post', 'put', 'delete'])) {
            throw new FengRequestException("请求的方法不支持 $method", 405);
        }
        if (empty($url)) {
            throw new FengRequestException("请求的地址 url 不能为空", 404);
        }
        $this->asyncTasks[$name] = [
            'method' => $method,
            'url' => $url,
            'params' => array_merge($_REQUEST['operation_info'] ?? [], $params),
            'options' => $options
        ];
    }

    /**
     * 默认不抛出异常，将错误内容返回给结果
     * @return array|mixed
     * @throws FengRequestException
     */
    public function runAsync()
    {
        if (empty($this->asyncTasks)) {
            return [];
        }
        if (count($this->asyncTasks) > 100) {
            throw new FengRequestException('最大并发请求数不能超过100个');
        }
        $promises = [];
        foreach ($this->asyncTasks as $name => $request) {
            $method = array_get($request, 'method');
            $url = array_get($request, 'url');
            $params = array_get($request, 'params');
            $options = array_get($request, 'options', []);
            if (strtoupper($method) === 'GET') {
                $qs = 'query';
            } else {
                $qs = 'json';
            }
            $methodAsync = $method . 'Async';
            $params = array_merge(['from' => get_from()], $params);
            $options = array_merge($options, [$qs => $params]);
            $options = $this->buildHeader($options);
            $promises[$name] = $this->client->$methodAsync($url, $options);
        }

        $begin = microtime(true);
        $results = Promise\settle($promises)->wait();
        $elapsed = $this->getElapsed($begin);

        foreach ($results as $name => &$value) {
            $state = array_get($value, 'state');
            $detail = json_encode(array_get($this->asyncTasks, $name));
            if ($state == 'fulfilled') {
                $response = array_get($value, 'value');

                $value = json_decode($response->getBody()->getContents(), true);
                if (isset($value['code']) && $value['code'] !== 0 && config('feng_request.throw_exception', true)) {
                    Log::error('async code:' . $value['code'] . ' time:' . $elapsed . ' detail:' . $detail
                        . ' msg:'.array_get($value, 'msg', '请求出错'));
                    throw new FengRequestException(array_get($value, 'msg', '请求出错'));
                } else {
                    Log::info('async code:' . array_get($value, 'code', $response->getStatusCode())
                        . ' time:' . $elapsed . ' detail:' . $detail);
                }
            } else {
                $e = array_get($value, 'reason');
                $response = $e->getResponse();

                Log::error('async code:' . $e->getCode() . ' time:' . $elapsed . ' detail:' . $detail
                    . ' msg:'.$e->getMessage());

                if (config('feng_request.throw_exception', true)) {
                    throw new FengRequestException($e->getMessage());
                } elseif (!empty($response->getBody()) && !empty($response->getBody()->getContents())) {
                    $value = json_decode($response->getBody()->getContents(), true);
                } else {
                    $value = [
                        'code' => 1,
                        'msg' => $e->getMessage()
                    ];
                }
            }
        }

        $this->asyncTasks = [];
        return $results;
    }

    public function getAsyncTasks()
    {
        return $this->asyncTasks;
    }

    /**
     * 返回消耗时间
     * @param  float  $begin  开始时间 microtime(true)
     * @return  string  保留小数 2 位，单位: ms
     */
    protected function getElapsed($begin)
    {
        return sprintf('%.2f', (microtime(true) - $begin) * 1000);
    }

    /**
     * 添加透传header
     *
     * @param  Bool  $raw  是否原始输出（原 http 协议格式）
     *
     * @return array
     */
    public function addHeader($raw = false)
    {
        $headers = array();
        if ($raw) {
            $headers[] = 'X-REQUEST-ID: ' . $this->traceId;
            $headers[] = 'X-REQUEST-RPCID: ' . $this->getTargetRpcId();
            $headers[] = 'X-USERID: ' . $this->getUserId();
            $headers[] = 'FROM: ' . get_from();
            $headers[] = 'X_FORWARDED_FOR: ' . get_client_ip();
        } else {
            $headers['X-REQUEST-ID'] = $this->traceId;
            $headers['X-REQUEST-RPCID'] = $this->getTargetRpcId();
            $headers['X-USERID'] = $this->getUserId();
            $headers['FROM'] = get_from();
            $headers['X_FORWARDED_FOR'] = get_client_ip();
        }

        return $headers;
    }

    /**
     * 手动设置本地自增 rpcId
     * 主要使用在 daemon 形式的项目中（mq)
     *
     * @param integer $int
     */
    public function setTargetIncreaseRpcId($int = 1)
    {
        $this->targetIncreaseRpcId = $int;
        return $this;
    }
}
