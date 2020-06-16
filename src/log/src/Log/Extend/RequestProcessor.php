<?php

namespace Feng\Log\Extend;

/**
 * 追加输出字段
 * 通用请求信息字段
 */
class RequestProcessor
{
    protected static $requestId;
    protected static $from;
    protected static $host;
    protected static $uri;
    protected static $clientIp;
    protected static $uid;
    protected static $param;

    /**
     * 是否显示请求参数标识
     * @var boolean
     */
    protected $paramDisplay;

    public function __construct($paramDisplay = false)
    {
        $this->paramDisplay = $paramDisplay;
    }

    public function __invoke(array $record)
    {
        if (php_sapi_name() == 'cli') {
            $record = $this->appendConsoleExtraFields($record);
        } else {
            $record = $this->appendExtraFields($record);
        }
        return $record;
    }

    public function appendConsoleExtraFields(array $record)
    {
        $record['app_name'] = config('app.app_name', basename(base_path()));
        $record['requestId'] = $this->getRequestId();
        $record['from'] = $this->getFrom();
        $record['host'] = $this->getHost();
        $record['uri'] = $this->getUri();
        $record['clientIp'] = $this->getClientIp();
        $record['uid'] = $this->getUid();

        $record['param'] = $this->getConsoleParam();
        return $record;
    }

    public function appendExtraFields(array $record)
    {
        $record['app_name'] = config('app.app_name', basename(base_path()));
        $record['requestId'] = $this->getRequestId();
        $record['from'] = $this->getFrom();
        $record['host'] = $this->getHost();
        $record['uri'] = $this->getUri();
        $record['clientIp'] = $this->getClientIp();
        $record['uid'] = $this->getUid();

        if ($this->paramDisplay === true) {
            $record['param'] = $this->getParam();
        } else {
            $record['param'] = '0';
        }

        return $record;
    }

    protected function getRequestId()
    {
        if (is_null(self::$requestId)) {
            $requestIdConf = config('fenglog.trace_id');
            self::$requestId = empty($_SERVER[$requestIdConf]) ? '0' : $_SERVER[$requestIdConf];
        }
        return self::$requestId;
    }

    protected function getFrom()
    {
        if (is_null(self::$from)) {
            self::$from = empty($_SERVER['HTTP_FROM']) ? '0' : $_SERVER['HTTP_FROM'];
        }
        return self::$from;
    }

    protected function getClientIp()
    {
        if (is_null(self::$clientIp)) {
            self::$clientIp = get_client_ip();
        }
        return self::$clientIp;
    }

    protected function getHost()
    {
        if (is_null(self::$host)) {
            self::$host = gethostname() ? gethostname() : 'cli';
        }
        return self::$host;
    }

    protected function getUri()
    {
        if (is_null(self::$uri)) {
            self::$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'cli';
        }
        return self::$uri;
    }

    protected function getUid()
    {
        if (is_null(self::$uid)) {
            self::$uid = empty($_SERVER['HTTP_UID']) ? '0' : $_SERVER['HTTP_UID'];
        }
        return self::$uid;
    }

    protected function getParam()
    {
        if (is_null(self::$param)) {
            if (strtoupper($_SERVER['REQUEST_METHOD']) === 'GET') {
                self::$param = json_encode($_GET);
            } else {
                self::$param = json_encode($_POST);
            }
        }
        return self::$param;
    }

    protected function getConsoleParam()
    {
        if (is_null(self::$param)) {
            $argv = $_SERVER['argv'];
            array_shift($argv);
            self::$param = json_encode($argv);
        }
        return self::$param;
    }
}
