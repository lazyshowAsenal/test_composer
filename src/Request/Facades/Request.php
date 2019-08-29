<?php

namespace Feng\Utils\Request\Facades;

use Illuminate\Support\Facades\Facade;

class Request extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fengrequest';
    }
}
