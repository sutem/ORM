<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;
use Zend\Db\Sql\Ddl\Column\Text as ZendText;

class Text extends ZendText implements ColumnInterface
{
    use ColumnContract;
    use Mapper\Reference\ReferenceMapContract;
}
