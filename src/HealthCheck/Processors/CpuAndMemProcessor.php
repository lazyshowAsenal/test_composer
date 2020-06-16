<?php

namespace Lzx\Utils\HealthCheck\Processors;


class CpuAndMemProcessor implements ProcessorInterface
{
    private $config;

    public function __construct()
    {
        $this->config = null;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getUsage()
    {
        // TODO: Implement handle() method.
    }
}