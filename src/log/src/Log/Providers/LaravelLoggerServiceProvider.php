<?php

namespace Feng\Log\Providers;

use Illuminate\Support\ServiceProvider;
use Feng\Log\Logger;

class LaravelLoggerServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'fenglog';

    protected $defer = false;

    public function register()
    {
        $configPath = __DIR__ . '/../../config/fenglog.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);
        $this->app->singleton('fenglog', function ($app) {
            return new Logger;
        });
    }

    public function boot()
    {
        $this->app->make(self::CONFIG_KEY);
        $this->app->singleton('Psr\Log\LoggerInterface', function ($app) {
            return $app->make(self::CONFIG_KEY);
        });
    }
}
