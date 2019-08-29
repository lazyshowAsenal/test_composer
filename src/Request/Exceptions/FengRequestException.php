<?php

namespace Feng\Utils\Request\Exceptions;

use Exception;

class FengRequestException extends Exception
{
    private $logLevel;

    public function __construct($message, $code = null)
    {
        $this->message = $message;
        $this->code = ($code == null ? 1 : $code);
        $this->logLevel = 'error';
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }
}
