<?php

namespace Feng\Log\Processors;

use Feng\Log\Handlers\Handler;
use Psr\Log\LogLevel;
use ErrorException;
use Feng\Log\Extend\RequestProcessor;
use Feng\Log\Extend\CustomProcessor;


class ExceptionLogProcessor extends Processor
{
    const DEFAULT_EXT_FORMAT = 'totalTime:{totalTime} file:{file} line:{line} error:[{error}]';

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
        $enabled = config('fenglog.exception_log.enabled', false);
        if (!$enabled) {
            return;
        }
        $this->handler = Handler::getInstance('exception');
        $this->configureHandlers($this->handler);
        set_error_handler([$this, 'handleError']);
        $this->logger = $logger;
        $this->logger->pushAsyncTask(array($this, 'handleFinish'));
    }

    public function handleFinish()
    {
        parent::handleFinish();

        $this->handleException();
    }

    public function getPath()
    {
        if ($this->path) {
            return $this->path;
        }
        $this->path = config('fenglog.exception_log.path');
        return $this->path;
    }

    public function getType()
    {
        if ($this->type) {
            return $this->type;
        }
        $this->type = config('fenglog.exception_log.type');
        return $this->type;
    }

    public function getFormat()
    {
        if (is_null($this->format)) {
            $this->format = config('fenglog.exception_log.format', self::DEFAULT_EXT_FORMAT);
        }
        return $this->format;
    }

    public function handleException()
    {
        $error = error_get_last();
        if (is_null($error)) {
            return;
        }

        $level = $error['type'];
        $error = [
            '{error}' => $error['message'],
            '{file}' => $error['file'],
            '{line}' => $error['line']
        ];
        $this->writeLog($level, $error);
    }

    /**
     * overwrite framework error handle
     *
     * @param  integer $level error no
     * @param  string $message error message
     * @param  string $file which contains the filename that the error was raised in
     * @param  integer $line which contains the line number the error was raised at
     * @param  array $context
     * @return void
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        $context = [
            '{error}' => $message,
            '{file}' => $file,
            '{line}' => $line
        ];
        $this->writeLog($level, $context);

        if (strpos(app('app')->version(), 'Lumen') === false) {
            // return to the framework logic
            // Illuminate\Foundation\Bootstrap\HandleExceptions@handleError
            $frameworkExceptionHandle = app('Illuminate\Foundation\Bootstrap\HandleExceptions');
            $frameworkExceptionHandle->handleError($level, $message, $file, $line, $context);
        } else {
            if (error_reporting() & $level) {
                throw new ErrorException($message, 0, $level, $file, $line);
            }
        }
    }

    public function writeLog($level = 1, array $context = [])
    {
        if ($this->getType() === 'fluentd') {
            $this->writeFluentd($level, $context);
        } else {
            $this->writeFile($level, $context);
        }
    }

    protected function writeFluentd($level, $context)
    {
        $this->handler->pushProcessor(new RequestProcessor());

        $this->handler->pushProcessor(new CustomProcessor($context));
        $levelMap = $this->getLevelMap();
        if (in_array($level, array_keys($levelMap))) {
            $method = $levelMap[$level];
        } else {
            $method = LogLevel::CRITICAL;
        }
        $this->handler->{$method}('');
    }

    protected function writeFile($level = 1, array $context = [])
    {
        $replace = [
            '{traceId}' => $this->getTraceId(),
            '{operatorId}' => $this->getOperatorId(),
            '{operatorType}' => $this->getOperatorType(),
            '{from}' => $this->getFrom(),
            '{host}' => $this->getHost(),
            '{uri}' => $this->getUri(),
            '{clientIp}' => $this->getClientIp(),
            '{asyncTime}' => $this->getAsyncTime(),
            '{totalTime}' => $this->getTotalTime()
        ];
        $levelMap = $this->getLevelMap();
        if (in_array($level, array_keys($levelMap))) {
            $method = $levelMap[$level];
        } else {
            $method = LogLevel::CRITICAL;
        }
        $replace = array_merge($replace, $context);
        $message = $this->getDefaultFormat() . $this->getFormat();
        $result = strtr($message, $replace);
        $this->handler->{$method}($result);
    }

    protected function getLevelMap()
    {
        return array(
            E_ERROR => LogLevel::CRITICAL,
            E_WARNING => LogLevel::WARNING,
            E_PARSE => LogLevel::ALERT,
            E_NOTICE => LogLevel::NOTICE,
            E_CORE_ERROR => LogLevel::CRITICAL,
            E_CORE_WARNING => LogLevel::WARNING,
            E_COMPILE_ERROR => LogLevel::ALERT,
            E_COMPILE_WARNING => LogLevel::WARNING,
            E_USER_ERROR => LogLevel::ERROR,
            E_USER_WARNING => LogLevel::WARNING,
            E_USER_NOTICE => LogLevel::NOTICE,
            E_STRICT => LogLevel::NOTICE,
            E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_DEPRECATED => LogLevel::NOTICE,
            E_USER_DEPRECATED => LogLevel::NOTICE,
        );
    }
}
