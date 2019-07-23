<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;
use Zend\Db\Sql\Ddl\Column\BigInteger as ZendBigInteger;

class BigInteger extends ZendBigInteger implements ColumnInterface
{
    use ColumnContract;
    use Mapper\Reference\ReferenceMapContract;
}
