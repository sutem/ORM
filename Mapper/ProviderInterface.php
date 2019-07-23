<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper;

use Qant\ORM;

interface ProviderInterface
{
    /**
     * get
     *
     * @param string $_entity
     */
    public function get(string $_entity) : Mapper;

    /**
     * initialize
     *
     * @param ORM\ModelManager $_mm
     */
    public function initialize(ORM\ModelManager $_mm) : void;
}
