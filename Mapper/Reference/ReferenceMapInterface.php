<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Reference;

interface ReferenceMapInterface
{
    /**
     * __invoke
     *
     * @param mixed $_referenceMap
     */
    public function __invoke($_referenceMap) : array;
}
