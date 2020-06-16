<?php

namespace Feng\Log;

use Feng\Log\Processors\DBTransLogProcessor;
use Feng\Log\Processors\RequestLogProcessor;
use Feng\Log\Processors\BaseLogProcessor;
use Feng\Log\Processors\ExceptionLogProcessor;
use Feng\Log\Processors\SQLLogProcessor;
use Feng\Log\Processors\CustomLogProcessor;
use Feng\Log\Exceptions\LogBadMethodCallException;

class Logger
{
    /**
     * base log handler
     * @var array
     */
    protected $handler;

    /**
     * custom path for temporary
     * @var array
     */
    protected $custom = [];

    /**
     * 异步记录日志任务列表
     * @var array
     */
    protected $asyncTasks = [];

    public function __construct()
    {
        $this->registerBaseLog();

        if ($this->checkConsole()) {
            return;
        }

        $this->registerRequestLog();
        $this->registerExceptionLog();
        $this->registerSQLLog();
        $this->registerDBTransLog();
        $this->initAsyncTasks();
    }

    /**
     * register base log for request
     *
     * @return void
     */
    public function registerRequestLog()
    {
        $processor = RequestLogProcessor::getInstance();
        $processor->boot($this);
    }

    /**
     * register base log
     *
     * @return void
     */
    protected function registerBaseLog()
    {
        $processor = BaseLogProcessor::getInstance();
        $processor->boot($this);
        $this->handler = $processor;
    }

    /**
     * register exception log handle fatal crash
     * @return void
     */
    protected function registerExceptionLog()
    {
        $processor = ExceptionLogProcessor::getInstance();
        $processor->boot($this);
    }

    protected function registerSQLLog()
    {
        $processor = SQLLogProcessor::getInstance();
        $processor->boot($this);
    }

    protected function registerDBTransLog()
    {
        $processor = DBTransLogProcessor::getInstance();
        $processor->boot($this);
    }

    protected function initAsyncTasks()
    {
        register_shutdown_function(array($this, 'handleAsyncTasks'));
    }

    public function getAsyncTasks()
    {
        return $this->asyncTasks;
    }

    public function pushAsyncTask(array $task)
    {
        $this->asyncTasks[] = $task;
    }

    /**
     * 处理异步日志任务
     * @return void
     */
    public function handleAsyncTasks()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $tasks = (array)$this->getAsyncTasks();
        foreach ($tasks as $task) {
            call_user_func($task);
        }
    }

    /**
     * skip console step
     *
     * @return boolean
     */
    protected function checkConsole()
    {
        if (php_sapi_name() != 'cli') {
            return false;
        }
        // 命令行下默认同步输出日志
        config(['fenglog.base_log.async' => false]);

        $args = $argv = $_SERVER['argv'];

        $command = array_shift($args);
        if (strpos($command, 'phpunit') != false) {
            config([
                'fenglog.request_log.enabled' => false,
                'fenglog.exception_log.enabled' => false,
            ]);

            return false;
        }

        if (count($args) === 0) {
            return true;
        }
        $filter = ['clear-compiled', 'optimize'];
        $command = array_shift($args);
        if (strpos($command, '-') === 0) {
            return true;
        }
        if (in_array($command, $filter)) {
            return true;
        }

        return false;
    }

    /**
     * Write a message to Monolog.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    protected function writeLog($level, $message, $context)
    {
        $this->handler->{$level}($message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a business warning message to the logs.
     *
     * @param  string $message
     * @param  string $businessGroup
     * @param  array $context
     * @return void
     */
    public function businessWarning($message, $businessGroup, array $context = [])
    {
        $warningTag = config('fenglog.warning_tag.business', '业务报警');

        $message = sprintf("$warningTag:%s:%s", "$businessGroup", $message);
        $this->writeLog('warning', $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog(__FUNCTION__, $message, $context);
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
        $this->writeLog($level, $message, $context);
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
        $this->writeLog($level, $message, $context);
    }

    public function setPath($path)
    {
        $this->handler->setPath($path);
    }

    /**
     * 使用自定义路径日志，使用 BaseLog 的配置
     * @example Log::custom('cron', 'save_path')->info($message)
     * @param  string $channel 频道名称和文件名一致
     * @param  string $path 保存路径
     * @return \Feng\Log\Processors\CustomProcessor
     */
    public function custom($channel = 'base', $path = '')
    {
        if (isset($this->custom[$channel])) {
            return $this->custom[$channel];
        }
        if (!config('fenglog.base_log.enabled', false)) {
            return $this->handler;
        }
        $this->custom[$channel] = new CustomLogProcessor;
        $this->custom[$channel]->setChannel($channel);
        $this->custom[$channel]->boot($this);
        // 如果类型是 fluentd 则替换自定义路径为 sock 地址
        // 使用 BaseLog 的地址配置
        if ($this->handler->getType() === 'fluentd') {
            $path = $this->handler->getPath();
        }
        $this->custom[$channel]->setPath($path);
        return $this->custom[$channel];
    }

    public function __call($method, $args)
    {
        // 兼容老方法
        if ($method === 'useDailyFiles') {
            $path = (string)array_shift($args);
            $this->setPath($path);
            return;
        }
        throw new LogBadMethodCallException("Call to undefined method [$method]");
    }
}
