<?php

declare(strict_types=1);

namespace Qant\ORM\Entity;

use Qant\ORM;

interface ProviderInterface
{
    /**
     * initialize
     *
     * @param ORM\ModelManager $_mm
     */
    public function initialize(ORM\ModelManager $_mm) : void;
}
