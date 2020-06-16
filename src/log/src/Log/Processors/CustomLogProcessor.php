<?php
namespace Feng\Log\Processors;

use Feng\Log\Handlers\Handler;
use Feng\Log\Extend\RequestProcessor;
use Feng\Log\Extend\CustomProcessor;

/**
 * 自定义日志处理
 * 主要用来多个路径多存日志
 */
class CustomLogProcessor extends Processor
{
    const DEFAULT_EXT_FORMAT = 'file:{file} line:{line} message:[{message}]';

    protected $maxFileLength = 102400;

    protected $blackUriList = array();

    protected $channel = 'base';

    public function __construct()
    {
        $this->traceId = $this->getTraceId();
        $this->operatorType = $this->getOperatorType();
        $this->operatorId = $this->getOperatorId();
        $this->host = $this->getHost();
        $this->from = $this->getFrom();
        $this->uri = $this->getUri();
        $this->defaultFormat = $this->getDefaultFormat();
        $this->format = $this->getFormat();
    }

    public function boot($logger)
    {
        $enabled = config('fenglog.base_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $channel = $this->getChannel();
        $this->handler = Handler::getInstance($channel);
        $this->logger = $logger;
        $this->configureHandlers($this->handler);
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function setChannel($channel = 'base')
    {
        // 为自定义统一 channel 前缀方便收集
        $this->channel = 'custom.' . $channel;
        return $this;
    }

    public function getPath()
    {
        if ($this->path) {
            return $this->path;
        }
        $this->path = dirname(config('fenglog.custom_log.path', storage_path('logs/custom/custom.log')));
        $this->path = $this->path . DIRECTORY_SEPARATOR . $this->getChannel() . '.log';
        return $this->path;
    }

    public function setPath($path)
    {
        if (empty($path)) {
            $path = $this->getPath();
        }
        $this->handler->setPath($path);
        $this->handler->configureHandlers();
        return $this;
    }

    /**
     * 使用 BaseLog 的类型
     * @return string
     */
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

    protected function writeFluentd($level, $message, $context, $callPlace, $occurTime = null)
    {
        $result = $this->formatMessage($message);
        $this->handler->pushProcessor(new RequestProcessor());

        $this->handler->pushProcessor(new CustomProcessor($callPlace));
        $this->handler->{$level}($result, $context);
    }

    protected function writeLog($level, $message, $context)
    {
        $callPlace = $this->getCallPlace();
        $occurTime = get_micro_datetime();
        if ($this->getType() === 'fluentd') {
            $this->writeFluentd($level, $message, $context, $callPlace, $occurTime);
        } else {
            $this->output($level, $message, $context, $callPlace, $occurTime);
        }
    }

    /**
     * Write a message to Monolog.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @param        $callPlace
     * @param string $occurTime
     *
     * @return void
     */
    protected function output($level, $message, $context, $callPlace, $occurTime)
    {
        $message = $this->formatMessage($message);
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $file = null;
        $line = null;
        foreach ($stack as $trace) {
            $file = isset($trace['file']) ? $trace['file'] : $file;
            $line = isset($trace['line']) ? $trace['line'] : $line;
            if (isset($trace['class']) && strpos($trace['class'], 'Yunniao') === false) {
                break;
            }
        }
        $replace = [
            '{traceId}' => $this->getTraceId(),
            '{operatorId}' => $this->getOperatorId(),
            '{operatorType}' => $this->getOperatorType(),
            '{from}' => $this->getFrom(),
            '{host}' => $this->getHost(),
            '{uri}' => $this->getUri(),
            '{clientIp}' => $this->getClientIp(),
            '{totalTime}' => $this->getTotalTime(),
            '{occurTime}' => $occurTime,
            '{file}' => $file,
            '{line}' => $line,
            '{message}' => $message
        ];
        $format = $this->getDefaultFormat() . $this->getFormat();
        $result = strtr($format, $replace);
        $this->handler->{$level}($result, $context);
    }


    /**
     * Log an emergency message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an alert message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a critical message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an error message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a warning message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a notice to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an informational message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a debug message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a message to the logs.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        return $this->writeLog($level, $message, $context);
    }

    /**
     * Dynamically pass log calls into the writer.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function write($level, $message, array $context = [])
    {
        return $this->writeLog($level, $message, $context);
    }
}
