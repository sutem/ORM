<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;
use Zend\Db\Sql\Ddl\Column\Decimal as ZendDecimal;

class Decimal extends ZendDecimal implements ColumnInterface
{
    use ColumnContract;
    use Mapper\Reference\ReferenceMapContract;
}
