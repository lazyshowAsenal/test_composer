<?php

namespace Feng\Utils\Hbase\Providers;


use Feng\Utils\Hbase\HbaseUtil;
use Illuminate\Support\ServiceProvider;

class HbaseProvider extends ServiceProvider
{
    const CONFIG_KEY = 'feng_hbase';

    protected $defer = true;

    public function register()
    {

        $this->app->configure(self::CONFIG_KEY);

        $configPath = __DIR__ . '/../../../config/feng_hbase.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);

        $this->app->singleton('fenghbaseutil', function ($app) {

            return HbaseUtil::getInstance();
        });
    }
}