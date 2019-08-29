<?php

namespace Feng\Utils\Request\Providers;

use Feng\Utils\Request\Request;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;

class LumenRequestProvider extends ServiceProvider
{
    const CONFIG_KEY = 'feng_request';

    protected $defer = true;

    public function register()
    {
        $this->app->configure(self::CONFIG_KEY);
        $configPath = __DIR__ . '/../../config/feng_request.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);
        $this->app->singleton('fengrequest', function ($app) {
            return new Request(new Client(
                [
                    'timeout' => $app['config']['feng_request.timeout'],
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
                ]));
        });
    }

}
