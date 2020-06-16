<?php

namespace Feng\Log\Handlers;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Monolog\Logger as Monolog;
use Feng\Log\Extend\LineFormatter;
use Feng\Log\Extend\FluentdFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Fluent\Logger\FluentLogger;
use Feng\Log\Extend\FluentdHandler;
use Feng\Log\Exceptions\LogUnexpectedValueException;
use Feng\Log\Exceptions\LogInvalidArgumentException;

class MonologHandler extends Handler implements PsrLoggerInterface
{
    private $handler;

    private $pushHandler;

    public function __construct($channel = 'local')
    {
        $this->handler = new Monolog($channel);
    }

    public function setPath($path)
    {
        if (strlen($path) === 0) {
            throw new LogInvalidArgumentException("Path is empty");
        }
        $this->path = $path;
    }

    /**
     * get output path of request log
     *
     * @return string output path
     */
    public function getPath()
    {
        return $this->path;
    }

    public function setType($type)
    {
        if (!in_array($type, $this->typeList)) {
            throw new LogUnexpectedValueException("Unsupported type [{$type}]");
        }
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * configure request log setting
     *
     * @return void
     */
    public function configureHandlers()
    {
        $type = $this->getType();
        if ($type === 'fluentd') {
            $method = 'use' . ucfirst($type);
        } else {
            $method = 'use' . ucfirst($type) . 'Files';
        }
        $path = $this->getPath();
        $this->handler->setHandlers([]);
        $this->{$method}($path);
    }

    public function pushProcessor($callback)
    {
        $this->handler->pushProcessor($callback);
    }

    /**
     * 使用 fluentd 进行日志记录
     * @param  string $path 域套接字地址 e.g. unix:///var/run/td-agent/td-agent.sock
     * @param  string $level
     * @return void
     */
    public function useFluentd($path, $level = 'debug')
    {
        $logger = new FluentLogger($path);
        $this->handler->pushHandler($handler = new FluentdHandler($logger));

        $handler->setFormatter($this->getFluentdFormatter());
    }

    /**
     * Register a file log handler.
     *
     * @param  string $path
     * @param  string $level
     * @return void
     */
    public function useSingleFiles($path, $level = 'debug')
    {
        $this->handler->pushHandler($handler = new StreamHandler($path));

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Register a daily file log handler.
     *
     * @param  string $path
     * @param  int $days
     * @param  string $level
     * @return void
     */
    public function useDailyFiles($path, $days = 0, $level = 'debug')
    {
        $this->handler->pushHandler(
            $handler = new RotatingFileHandler($path, $days)
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Register a hourly file log handler
     *
     * @param  string $path output path
     * @param  integer $hours split per hour
     * @param  string $level level
     * @return void
     */
    public function useHourlyFiles($path, $hours = 1, $level = 'debug')
    {
        $hours = env('APP_REQUEST_LOG_HOURLY', 1);
        $path = $this->getHourlyPath($path, $hours);
        $this->handler->pushHandler($handler = new StreamHandler($path));

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Get a defaut Monolog formatter instance.
     *
     * @return \Monolog\Formatter\LineFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter(null, null, false, true);
    }

    /**
     * Get a fluentd Monolog formatter instance.
     * @return \Feng\Log\Extend\FluentdFormatter
     */
    protected function getFluentdFormatter()
    {
        return new FluentdFormatter(true);
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
     * Write a message to Monolog.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @todo   listen event
     * @return void
     */
    protected function writeLog($level, $message, $context)
    {
        $this->handler->{$level}($message, $context);
    }
}
