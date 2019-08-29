<?php

namespace Feng\Utils\Queue\Facades;

use Illuminate\Support\Facades\Facade;

class FengQueue extends Facade
{

    protected static function getFacadeAccessor()
    {
        // 目前仅有一个消息队列的实现，所以这里写死 fengcmqutil
        return 'fengcmqutil';
    }
}
