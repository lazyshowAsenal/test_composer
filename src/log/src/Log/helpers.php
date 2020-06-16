<?php

if (! function_exists('get_client_ip')) {
    /**
     * 获取客户端 IP
     * @return string 客户端 IP
     */
    function get_client_ip() {

        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key)
        {
            if (array_key_exists($key, $_SERVER) === true)
            {
                foreach (explode(',', $_SERVER[$key]) as $ipAddress)
                {
                    $ipAddress = trim($ipAddress);

                    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
                    {
                        return $ipAddress;
                    }
                    return $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // 如果前面都没有取到，返回默认值
        return '127.0.0.1';
    }
}

if (! function_exists('get_micro_datetime')) {
    /**
     * 获取带微秒的时间格式
     * @return string 带微秒的时间格式
     */
    function get_micro_datetime() {
        $microtime = microtime(TRUE);

        return get_micro_datetime_from_microtime($microtime);
    }
}

if (! function_exists('get_micro_datetime_from_microtime')) {
    /**
     * 获取带微秒的时间格式
     * @param string $microtime U.u格式的时间戳
     * @return string 带微秒的时间格式
     */
    function get_micro_datetime_from_microtime($microtime) {
        try{
            $dateFormat = 'Y-m-d H:i:s.u';
            $timeZone = 'PRC';
            if (function_exists('env')){
                $timeZone = env('APP_TIMEZONE', 'PRC');
            }

            $dateObj = DateTime::createFromFormat('U.u', round($microtime, 4));
            if (is_object($dateObj)){
                $dateObj->setTimeZone(new DateTimeZone($timeZone));
                return $dateObj->format($dateFormat);
            }

            $date = date($dateFormat, $microtime);
            if ($date !== false){
                return $date;
            }
        }catch (\Exception $e){
            // do nothing
        }

        //如果前面都没取到值，返回当前时间
        return date($dateFormat);
    }
}
