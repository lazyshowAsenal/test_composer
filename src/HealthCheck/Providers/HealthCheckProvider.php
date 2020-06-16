<?php

namespace Lzx\Utils\HealthCheck\Providers;

use Illuminate\Support\ServiceProvider;
use Lzx\Utils\HealthCheck\HealthCheck;

class HealthCheckProvider extends ServiceProvider
{
    const CONFIG_KEY = 'health_check';

    protected $defer = true;

    public function register()
    {

        $this->app->configure(self::CONFIG_KEY);

        $configPath = __DIR__ . '/../../config/health_check.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);

        $this->app->singleton('healthcheckutil', function ($app) {

            return new HealthCheck(config(self::CONFIG_KEY));
        });
    }
}