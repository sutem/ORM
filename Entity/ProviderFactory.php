<?php

declare(strict_types=1);

namespace Qant\ORM\Entity;

use Psr\Container\ContainerInterface;

class ProviderFactory
{
    public function __invoke(ContainerInterface $_container) : Provider
    {
        return new Provider();
    }
}
