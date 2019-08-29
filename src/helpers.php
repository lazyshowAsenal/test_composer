<?php

if (! function_exists('get_from')) {
    /**
     * 获取 from
     * @param  integer $default  默认 From 值为 0
     * @return string
     */
    function get_from($default = 0) {
        return config('feng_request.from', $default);
    }
}

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
    }
}
if (! function_exists('get_local_ip')) {
    /**
     * 获取本地 IP
     * @return string 本地 IP
     */
    function get_local_ip() {

        foreach (array('LOCAL_ADDR', 'SERVER_ADDR',) as $key)
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
                    return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
                }
            }
        }

        return '127.0.0.1';
    }
}
