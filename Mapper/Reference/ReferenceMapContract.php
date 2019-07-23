<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Reference;

use Qant\ORM\Mapper;

trait ReferenceMapContract
{
    /**
     * __invoke
     *
     * @param mixed $_referenceMap
     */
    public function __invoke($_referenceMap) : array
    {
        $thisColumn = ($this instanceof Mapper\Table\TableInterface)
            ? $this->getColumn('id')
            : $this;

        if (is_array($_referenceMap)) {
            return array_merge([$thisColumn], array_values($_referenceMap));
        } elseif (is_object($_referenceMap)) {
            return [
                $thisColumn,
                ($_referenceMap instanceof Mapper\Table\TableInterface)
                    ? $_referenceMap->getColumn('id')
                    : $_referenceMap,
            ];
        }
    }
}
