<?php

namespace Feng\Utils\Cos\Facades;

use Illuminate\Support\Facades\Facade;

class FengCos extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'fengcosutil';
    }
}
