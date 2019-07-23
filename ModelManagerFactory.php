<?php

declare(strict_types=1);

namespace Qant\ORM;

use Qant\Application as QantApp;
use Qant\EventManager;
use Qant\Database\Adapter\Adapter;
use Psr\Container\ContainerInterface;

class ModelManagerFactory
{
    public function __invoke(ContainerInterface $_container) : ModelManager
    {
        return new ModelManager(
            QantApp::service(Adapter::class),
            QantApp::service(Gateway\Provider::class),
            QantApp::service(Entity\Provider::class),
            QantApp::service(Mapper\Provider::class),
            QantApp::service(EventManager\EventManager::class)
        );
    }
}
