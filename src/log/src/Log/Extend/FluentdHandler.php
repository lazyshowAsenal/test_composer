<?php
namespace Feng\Log\Extend;

use Fluent\Logger\FluentLogger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * 扩展 Monolog 加入 fluentd 写入支持
 */
class FluentdHandler extends AbstractProcessingHandler
{
    /**
     * @var \Fluent\Logger\FluentLogger
     */
    protected $logger;

    public function __construct(FluentLogger $logger, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->logger = $logger;
    }

    protected function write(array $record)
    {
        $tag = $record['channel'] . '.' . strtolower($record['level_name']);
        $this->logger->post($tag, $record['formatted']);
    }
}
