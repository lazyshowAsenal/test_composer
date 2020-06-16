<?php
namespace Feng\Log\Extend;

use Monolog\Formatter\LineFormatter as Formatter;

/**
 * 敏感信息出处过滤
 */
class LineFormatter extends Formatter
{

    public function format(array $record)
    {
        $output = parent::format($record);

        $pattern = [];
        $replace = [];
        foreach (config('fenglog.filter') as $value) {
            $pattern[] = '/"' . $value . '":".+?"/';
            $replace[] = '"' . $value . '":"*"';
        }

        $output = preg_replace($pattern, $replace, $output);

        return $output;
    }
}
