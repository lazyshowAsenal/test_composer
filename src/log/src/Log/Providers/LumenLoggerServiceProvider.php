<?php

namespace Feng\Log\Providers;

use Illuminate\Support\ServiceProvider;
use Feng\Log\Logger;

class LumenLoggerServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'fenglog';

    protected $defer = false;

    public function register()
    {
        $this->app->configure(self::CONFIG_KEY);
        $configPath = __DIR__ . '/../../config/fenglog.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);
        $this->app->singleton('fenglog', function ($app) {
            return new Logger;
        });
    }

    public function boot()
    {
        $this->app->make(self::CONFIG_KEY);
        if (isset($this->app->availableBindings['Psr\Log\LoggerInterface'])) {
            unset($this->app->availableBindings['Psr\Log\LoggerInterface']);
        }
        $this->app->singleton('Psr\Log\LoggerInterface', function ($app) {
            return $app->make(self::CONFIG_KEY);
        });
    }
}
