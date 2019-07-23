<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Driver;

use Qant\ORM\Mapper;

interface DriverInterface
{
    /**
     * setMapper
     *
     */
    public function setMapper(Mapper\Mapper $_mapper) : void;

    /**
     * prepareTables
     *
     */
    public function prepareTables() : void;

    /**
     * prepareReferences
     *
     */
    public function prepareReferences() : void;
}
