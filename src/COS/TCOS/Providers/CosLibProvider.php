<?php

namespace Feng\Utils\Cos\TCOS\Providers;

use Illuminate\Support\ServiceProvider;
use Feng\Utils\COS\TCOS\CosLib;
use Qcloud\Cos\Client;

class CosLibProvider extends ServiceProvider
{

    const CONFIG_KEY = 'feng_cos';

    protected $defer = true;

    public function register()
    {
        $this->app->configure(self::CONFIG_KEY);

        $configPath = __DIR__ . '/../../../config/feng_cos.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);

        $this->app->singleton('fengcosutil', function ($app) {
            $cosRegion = config('feng_cos.region');
            $secretID = config('feng_cos.secret_id');
            $secretKey = config('feng_cos.secret_key');

            return new CosLib($cosRegion, $secretID, $secretKey);
        });
    }

}
