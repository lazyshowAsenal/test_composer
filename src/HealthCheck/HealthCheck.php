<?php

namespace Lzx\Utils\HealthCheck;

/**
 * 健康检查
 *
 * Class HealthCheck
 *
 * @package Lzx\Utils
 */
class HealthCheck
{

    public function __construct(array $config)
    {

    }

    //cpu 使用百分比阈值
    const CPU_USAGE_PERCENT_WARNING_VALUE = 90;
    //内存 使用百分比阈值
    const MEMORY_USAGE_PERCENT_WARNING_VALUE = 90;
    //硬盘 使用百分比阈值
    const DISK_USAGE_PERCENT_WARNING_VALUE = 90;

    //cpu超过阈值返回状态码
    const HTTP_STATUS_CPU_BUSY_CODE = 590;
    //内存超过阈值返回状态码
    const HTTP_STATUS_MEM_BUSY_CODE = 580;

    //获取cpu和内存使用情况命令
    const GET_CPU_MEMORY_USAGE_INFO_CMD = 'top -b -n 2 | grep -E "(Cpu|Mem)"';

    public static function getCpuAndMemUsageInfo(): array
    {
        //获取某一时刻系统cpu和内存使用情况
        $fp = popen(self::GET_CPU_MEMORY_USAGE_INFO_CMD,"r");
        $rs = "";
        while(!feof($fp)){
            $rs .= fread($fp,1024);
        }
        pclose($fp);

        //提取时取top第二次打印的数据，因为第二次的结果相对第一次的结果，要准确一些
        preg_match_all("/%Cpu\(s\): (.*) us\,/", $rs,$cpus);
        $cpuUsagePercent = trim(array_get($cpus, '1.1', 0));

        preg_match_all("/KiB Mem :(.*) total\,.*free,(.*) used\,/", $rs, $memTotals);
        $memTotal = trim(array_get($memTotals, '1.1', 0));
        $memUsed = trim(array_get($memTotals, '2.1', 0));

        if ($memTotal == 0) {
            $memUsagePercent = 0;
        } else {
            $memUsagePercent = $memUsed / $memTotal * 100;
        }

        return [round($cpuUsagePercent,2), round($memUsagePercent,2)];
    }

    public function cpuCheck($cpuUsagePercent): bool
    {
        if ($cpuUsagePercent >= self::CPU_USAGE_PERCENT_WARNING_VALUE) {
            return false;
        }

        return true;
    }

    public function memCheck($memUsagePercent): bool
    {
        if ($memUsagePercent >= self::MEMORY_USAGE_PERCENT_WARNING_VALUE) {
            return false;
        }

        return true;
    }
}