<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper;

use Qant\Application as QantApp;
use Psr\Container\ContainerInterface;

class ProviderFactory
{
    public function __invoke(ContainerInterface $_container) : Provider
    {
        $provider = new Provider();

        foreach (QantApp::config('orm', []) as $namespace => $metadata) {
            $provider->registerMapper(new Mapper($namespace, new Driver\ArrayDriver($metadata)));
        }

        return $provider;
    }
}
