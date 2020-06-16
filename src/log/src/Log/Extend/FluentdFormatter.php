<?php

namespace Feng\Log\Extend;

use Monolog\Formatter\FormatterInterface;

/**
 *  对 Fluentd 方式输出格式化
 */
class FluentdFormatter implements FormatterInterface
{
    /**
     * @var bool $levelTag should message level be a part of the fluentd tag
     */
    protected $levelTag = false;

    public function __construct($levelTag = false)
    {
        if (!function_exists('json_encode')) {
            throw new \RuntimeException('PHP\'s json extension is required to use Monolog\'s FluentdUnixFormatter');
        }

        $this->levelTag = (bool)$levelTag;
    }

    public function isUsingLevelsInTag()
    {
        return $this->levelTag;
    }

    public function format(array $record)
    {
        $message = $record;
        $message['context'] = json_encode($record['context']);
        $message['extra'] = json_encode($record['extra']);
        $message['level'] = $record['level_name'];
        unset($message['datetime']);
        unset($message['level_name']);

        return $message;
    }

    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }
}
