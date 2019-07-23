<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;
use Zend\Db\Sql\Ddl\Column\Datetime as ZendDatetime;

class Datetime extends ZendDatetime implements ColumnInterface
{
    use ColumnContract;
    use Mapper\Reference\ReferenceMapContract;
}
