<?php

namespace Lzx\Utils\HealthCheck\Processors;


Interface ProcessorInterface
{
    public function __construct();

    public function setConfig(array $config);

    public function getConfig();

    public function getUsage();
}