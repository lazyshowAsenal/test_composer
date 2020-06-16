<?php

namespace Feng\Log\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Log\Writer
 */
class FengLog extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fenglog';
    }
}
