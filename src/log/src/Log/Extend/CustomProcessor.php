<?php

namespace Feng\Log\Extend;

/**
 * 添加自定义附加字段处理器
 * 每种日志类型所需不同所以做成动态的
 */
class CustomProcessor
{
    protected $extra;

    public function __construct(array $extra = [])
    {
        $this->extra = $extra;
    }

    public function __invoke(array $record)
    {
        // 如果有重复键名的先暂时放到这里
        $record['duplicateRemark'] = [];
        foreach ($this->extra as $key => $value) {
            if (strpos($key, '{') !== false) {
                $key = substr($key, 1, -1);
            }
            if (isset($record[$key])) {
                $record['duplicateRemark'][$key] = $value;
            } else {
                $record[$key] = $value;
            }
        }
        $record['duplicateRemark'] = json_encode($record['duplicateRemark']);

        return $record;
    }
}
