<?php

namespace Feng\Log\Processors;

use Closure;
use Feng\Log\Handlers\Handler;
use Feng\Log\Extend\RequestProcessor;
use Feng\Log\Extend\CustomProcessor;


class RequestLogProcessor extends Processor
{
    const REQUEST_LIMIT_SIZE = 8388608; //8 * 1024 * 1024 8M by default, Depends on php.ini post_max_size
    const DEFAULT_EXT_FORMAT = 'totalTime:{totalTime} asyncTime:{asyncTime} dbCount:{dbCount} dbTime:{dbTime} memPeakUsage:{memPeakUsage}';

    /**
     * fluentd 需要输出的字段
     * @var array
     */
    private $fluentdFields = ['totalTime', 'dbCount', 'dbTime', 'memPeakUsage'];

    private $startMemory;

    private $dbCount = 0;

    private $dbTime = 0;

    private $callback = null;

    private function __construct()
    {
        $this->startMemory = memory_get_usage();
        $this->traceId = $this->getTraceId();
        $this->host = $this->getHost();
        $this->from = $this->getFrom();
        $this->uri = $this->getUri();
        $this->defaultFormat = $this->getDefaultFormat();
        $this->format = $this->getFormat();
        $this->initQueryListen();
    }

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function boot($logger)
    {
        $enabled = config('fenglog.request_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $this->handler = Handler::getInstance('request');
        $this->configureHandlers($this->handler);
        $this->logger = $logger;
        $this->logger->pushAsyncTask(array($this, 'handleFinish'));
    }

    public function handleFinish()
    {
        parent::handleFinish();

        $this->writeLog();
    }

    public function getPath()
    {
        if ($this->path) {
            return $this->path;
        }
        $this->path = config('fenglog.request_log.path');
        return $this->path;
    }

    public function getType()
    {
        if ($this->type) {
            return $this->type;
        }
        $this->type = config('fenglog.request_log.type');
        return $this->type;
    }

    public function getOccurTime()
    {
        return $this->getStartDateTime();
    }

    public function writeFile()
    {
        $message = $this->getDefaultFormat() . $this->getFormat();
        $arrFormat = explode('{', $message);
        $replace = [];
        foreach ($arrFormat as $split) {
            if (!strpos($split, '}')) {
                continue;
            }
            $split = substr($split, 0, strpos($split, '}'));
            $method = 'get' . ucfirst($split);
            if (method_exists($this, $method)) {
                $replace['{' . $split . '}'] = $this->{$method}();
            }
        }
        $result = strtr($message, $replace);
        $this->handler->info($result);
    }

    public function writeFluentd()
    {
        $this->handler->pushProcessor(new RequestProcessor(true));

        $customFields = $this->getCustomFields();
        $this->handler->pushProcessor(new CustomProcessor($customFields));
        $this->handler->info('');
    }

    public function writeLog()
    {
        if ($this->getType() === 'fluentd') {
            $this->writeFluentd();
        } else {
            $this->writeFile();
        }
    }

    protected function getCustomFields()
    {
        $customRecords = [];
        foreach ($this->fluentdFields as $field) {
            $method = 'get' . ucfirst($field);
            if (method_exists($this, $method)) {
                $customRecords[$field] = $this->{$method}();
            }
        }

        return $customRecords;
    }

    public function getFormat()
    {
        if (is_null($this->format)) {
            $this->format = config('fenglog.request_log.format', self::DEFAULT_EXT_FORMAT);
        }
        return $this->format;
    }

    protected function getMemPeakUsage()
    {
        $this->memPeakUsage = round((memory_get_peak_usage() - $this->startMemory) / 1024 / 1024, 2);
        return $this->memPeakUsage;
    }

    protected function getDbTime()
    {
        return $this->dbTime;
    }

    protected function getDbCount()
    {
        return $this->dbCount;
    }

    protected function getListener()
    {
        if (strpos(app()->version(), '5.1') !== false) {
            return 'illuminate.query';
        } else {
            return \Illuminate\Database\Events\QueryExecuted::class;
        }
    }

    protected function getRequestParams()
    {
        $paramStr = json_encode(app('request')->all()) ?: '';
        $size = mb_strlen($paramStr, '8bit');

        return self::REQUEST_LIMIT_SIZE >= $size
            ? $paramStr
            : substr($paramStr, 0, self::REQUEST_LIMIT_SIZE) . '...}';
    }

    protected function getHeaderInfo()
    {
        $enable = env('REQUEST_LOG_HEADER', false);
        if (!$enable) {
            $headers = '';
        } else {
            $headers = app('request')->header();
        }


        return json_encode($headers);
    }

    protected function initQueryListen()
    {
        $events = app('events');
        $queryCollector = $this;
        $listener = $this->getListener();
        try {
            $events->listen($listener, function ($query, $bindings = null, $time = null, $connectionName = null,
                                                 $occurTime = null) use
            ($queryCollector) {
                if ($query instanceof \Illuminate\Database\Events\QueryExecuted) {
                    $bindings = $query->bindings;
                    $time = $query->time;
                    $connection = $query->connection;
                    $query = $query->sql;
                    $occurTime = get_micro_datetime_from_microtime(microtime(true)-($time/1000));
                } else {
                    $connection = app('db')->connection($connectionName);
                }
                $queryCollector->addQuery((string)$query, $bindings, $time, $connection, $occurTime);
            });
        } catch (\Exception $e) {
            throw new \Exception(
                'fenglog DB listen failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function addQuery($query = '', $bindings = null, $time = null, $connectionName = null, $occurTime =
    null)
    {
        if (!is_null($this->callback)) {
            call_user_func_array($this->callback, [$query, $bindings, $time, $connectionName, $occurTime]);
        }
        ++$this->dbCount;
        $this->dbTime += $time;
    }

    public function listen(Closure $callback)
    {
        $this->callback = $callback;
    }
}
