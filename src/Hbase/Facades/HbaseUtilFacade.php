<?php

namespace Feng\Utils\Hbase\Facades;

use Illuminate\Support\Facades\Facade;

class HbaseUtilFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'fenghbaseutil';
    }
}