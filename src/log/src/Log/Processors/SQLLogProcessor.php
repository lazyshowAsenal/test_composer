<?php

namespace Feng\Log\Processors;

use Feng\Log\Handlers\Handler;
use Feng\Log\Extend\RequestProcessor;
use Feng\Log\Extend\CustomProcessor;

class SQLLogProcessor extends Processor
{
    const DEFAULT_EXT_FORMAT = 'time:{time} sql:[{sql}]';

    private function __construct()
    {
        $this->traceId = $this->getTraceId();
        $this->operatorType = $this->getOperatorType();
        $this->operatorId = $this->getOperatorId();
        $this->host = $this->getHost();
        $this->from = $this->getFrom();
        $this->uri = $this->getUri();
        $this->defaultFormat = $this->getDefaultFormat();
        $this->format = $this->getFormat();
        $this->initListen();
    }

    public static function getInstance()
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    public function boot($logger)
    {
        $enabled = config('fenglog.sql_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $this->handler = Handler::getInstance('sql');
        $this->logger = $logger;
        $this->configureHandlers($this->handler);
    }

    public function getPath()
    {
        if ($this->path) {
            return $this->path;
        }
        $this->path = config('fenglog.sql_log.path');
        return $this->path;
    }

    public function getType()
    {
        if ($this->type) {
            return $this->type;
        }
        $this->type = config('fenglog.sql_log.type');
        return $this->type;
    }

    public function getFormat()
    {
        if (is_null($this->format)) {
            $this->format = config('fenglog.sql_log.format', self::DEFAULT_EXT_FORMAT);
        }
        return $this->format;
    }

    public function writeLog($query, $bindings = null, $time = null, $connectionName = null, $occurTime = null)
    {
        if ($this->getType() === 'fluentd') {
            $this->writeFluentd($query, $bindings, $time, $connectionName, $occurTime);
        } else {
            $this->writeFile($query, $bindings, $time, $connectionName, $occurTime);
        }
    }

    public function writeFluentd($query, $bindings = null, $time = null, $connectionName = null, $occurTime = null)
    {
        $this->handler->pushProcessor(new RequestProcessor(true));

        $customFields = [
            'sql' => $query,
            'time' => $time
        ];
        $this->handler->pushProcessor(new CustomProcessor($customFields));
        $this->handler->info('');
    }

    public function writeFile($query, $bindings = null, $time = null, $connectionName = null, $occurTime = null)
    {
        if (is_array($bindings)) {
            $query = str_replace('%', '%%', $query);
            $query = str_replace('?', "'%s'", $query);
            $query = vsprintf($query, $bindings);
        }

        $message = $this->getDefaultFormat() . $this->getFormat();
        $replace = [
            '{traceId}' => $this->getTraceId(),
            '{operatorId}' => $this->getOperatorId(),
            '{operatorType}' => $this->getOperatorType(),
            '{from}' => $this->getFrom(),
            '{host}' => $this->getHost(),
            '{uri}' => $this->getUri(),
            '{clientIp}' => $this->getClientIp(),
            '{occurTime}' => $occurTime,
            '{sql}' => $query,
            '{time}' => $time
        ];
        $result = strtr($message, $replace);
        $this->handler->info($result);
    }

    protected function initListen()
    {
        $enabled = config('fenglog.sql_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $request = $this->getRequestLogProcessor();
        $queryCollector = $this;
        $request->listen(function ($query, $bindings = null, $time = null, $connectionName = null, $occurTime =
        null) use
        ($queryCollector) {
            $queryCollector->writeLog($query, $bindings, $time, $connectionName, $occurTime);
        });
    }

    protected function getRequestLogProcessor()
    {
        return RequestLogProcessor::getInstance();
    }
}
