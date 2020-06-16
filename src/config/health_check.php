<?php

return [
    //阈值
    'cpu_usage_percent_waring_value'  => env('cpu_usage_percent_waring_value', 90),
    'mem_usage_percent_waring_value'  => env('mem_usage_percent_waring_value', 90),
    'disk_usage_percent_waring_value' => env('disk_usage_percent_waring_value', 90),

    //http返回状态码
    'http_status_cpu_busy'            => env('http_status_cpu_busy', 580),
    'http_status_memory_busy'         => env('http_status_memory_busy', 590),

    //命令行
    'get_cpu_and_mem_usage_cmd'       => 'top -b -n 2 | grep -E "(Cpu|Mem)"',
];