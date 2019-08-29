<?php

if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        $conf = [
            'feng_hbase' => require __DIR__ . '/../config/feng_hbase.php'
        ];

        return array_get($conf, $key, $default);
    }
}