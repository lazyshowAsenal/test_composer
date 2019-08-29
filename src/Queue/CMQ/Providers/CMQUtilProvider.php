<?php

namespace Feng\Utils\Queue\CMQ\Providers;

use Illuminate\Support\ServiceProvider;
use Feng\Utils\Queue\CMQ\CMQUtil;

class CMQUtilProvider extends ServiceProvider
{

    const CONFIG_KEY = 'feng_queue';

    protected $defer = true;

    public function register()
    {

        $this->app->configure(self::CONFIG_KEY);

        $configPath = __DIR__ . '/../../../config/feng_queue.php';
        $this->mergeConfigFrom($configPath, self::CONFIG_KEY);

        $this->app->singleton('fengcmqutil', function ($app) {

            $topicHost = config('feng_queue.cmq_topic_host');
            $queueHost = config('feng_queue.cmq_queue_host');
            $secretID = config('feng_queue.cmq_secret_id');
            $secretKey = config('feng_queue.cmq_secret_key');

            return new CMQUtil($topicHost, $queueHost, $secretID, $secretKey);
        });
    }

}
