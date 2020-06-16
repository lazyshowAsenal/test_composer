<?php

namespace Feng\Log\Processors;

use Feng\Log\Handlers\Handler;
use Feng\Log\Extend\RequestProcessor;
use Feng\Log\Extend\CustomProcessor;

class BaseLogProcessor extends Processor
{
    const DEFAULT_EXT_FORMAT = 'file:{file} line:{line} message:[{message}]';

    protected $maxFileLength = 102400;

    protected $blackUriList = array();

    protected $asyncLogs = [];

    private function __construct()
    {
        $this->traceId = $this->getTraceId();
        $this->host = $this->getHost();
        $this->from = $this->getFrom();
        $this->uri = $this->getUri();
        $this->defaultFormat = $this->getDefaultFormat();
        $this->format = $this->getFormat();
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
        $enabled = config('fenglog.base_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $this->handler = Handler::getInstance('base');
        $this->logger = $logger;
        $this->configureHandlers($this->handler);
        $this->logger->pushAsyncTask(array($this, 'handleFinish'));
    }

    /**
     * 处理异步任务
     * @return void
     */
    public function handleFinish()
    {
        parent::handleFinish();

        $this->handleAsyncWriteLog();
    }

    /**
     * 进行异步写入日志操作
     * @return void
     */
    public function handleAsyncWriteLog()
    {
        $asyncLogs = $this->getAsyncLogs();
        foreach ($asyncLogs as $log) {
            call_user_func_array(array($this, 'output'), $log);
        }
    }

    /**
     * 获取异步日志
     * @return array
     */
    public function getAsyncLogs()
    {
        return $this->asyncLogs;
    }

    /**
     * 异步日志内容压入栈
     * @param  string  $level
     * @param  string  $message
     * @param  array   $context
     * @param  array   $callPlace  调用地点
     * @param  string   $occurTime  实际发生时间
     * @return void
     */
    protected function pushAsyncLogs($level, $message, $context, $callPlace, $occurTime = '')
    {
        $this->asyncLogs[] = [$level, $message, $context, $callPlace, $occurTime];
    }

    /**
     * 将日志输出
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array   $context
     * @param  array   $callPlace  调用地点数组 ['file' => $file, 'line' => $line]
     * @param  string  $occurTime  日志实际发生时间
     * @return void
     */
    protected function output($level, $message, $context, $callPlace, $occurTime = '')
    {
        $message = $this->formatMessage($message);
        $replace = [
            '{traceId}' => $this->getTraceId(),
            '{operatorId}' => $this->getOperatorId(),
            '{operatorType}' => $this->getOperatorType(),
            '{from}' => $this->getFrom(),
            '{host}' => $this->getHost(),
            '{uri}' => $this->getUri(),
            '{clientIp}' => $this->getClientIp(),
            '{totalTime}' => $this->getTotalTime(),
            '{asyncTime}' => $this->getAsyncTime(),
            '{occurTime}' => $occurTime,
            '{file}' => isset($callPlace['file']) ? $callPlace['file'] : '',
            '{line}' => isset($callPlace['line']) ? $callPlace['line'] : '',
            '{message}' => $message
        ];
        $format = $this->getDefaultFormat() . $this->getFormat();
        $result = strtr($format, $replace);
        $this->handler->{$level}($result, $context);
    }

    public function getAsyncTime()
    {
        if (config('fenglog.base_log.async') === false) {
            return 0;
        }
        return parent::getAsyncTime();
    }

    public function getPath()
    {
        if ($this->path) {
            return $this->path;
        }
        $this->path = config('fenglog.base_log.path');
        return $this->path;
    }

    public function setPath($path)
    {
        $this->handler->setPath($path);
        $this->handler->configureHandlers();
    }

    public function getType()
    {
        if ($this->type) {
            return $this->type;
        }
        $this->type = config('fenglog.base_log.type');
        return $this->type;
    }

    public function getFormat()
    {
        if (is_null($this->format)) {
            $this->format = config('fenglog.base_log.format', self::DEFAULT_EXT_FORMAT);
        }
        return $this->format;
    }

    protected function writeFluentd($level, $message, $context, $callPlace, $microDatetime)
    {
        $result = $this->formatMessage($message);
        $this->handler->pushProcessor(new RequestProcessor());

        $this->handler->pushProcessor(new CustomProcessor($callPlace));
        $this->handler->{$level}($result, $context);
    }

    /**
     * Write a message to Monolog.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    protected function writeLog($level, $message, $context)
    {
        if (! config('fenglog.base_log.enabled', false)) {
            return;
        }
        $occurTime = get_micro_datetime();
        $callPlace = $this->getCallPlace();
        if ($this->getType() === 'fluentd') {
            $this->writeFluentd($level, $message, $context, $callPlace, $occurTime);
        } else {
            if (config('fenglog.base_log.async', true) === false) {
                $this->output($level, $message, $context, $callPlace, $occurTime);
            } else {
                $this->pushAsyncLogs($level, $message, $context, $callPlace, $occurTime);
            }
        }
    }

    /**
     * Log an emergency message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an alert message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a critical message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an error message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a warning message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a notice to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an informational message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a debug message to the logs.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a message to the logs.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        return $this->writeLog($level, $message, $context);
    }

    /**
     * Dynamically pass log calls into the writer.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function write($level, $message, array $context = [])
    {
        return $this->writeLog($level, $message, $context);
    }
}
