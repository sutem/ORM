<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;

interface ColumnInterface extends Mapper\Reference\ReferenceMapInterface
{
    public function setTable(Mapper\Table\Table $_table) : void;
    public function getTable() : Mapper\Table\Table;
}
