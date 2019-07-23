<?php

declare(strict_types=1);

namespace Qant\ORM\Sql;

use Zend\Db\Sql\Exception;
use Zend\Db\Sql\Sql as ZendSql;

class Sql extends ZendSql
{
    public function insert($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Insert(($table) ?: $this->table);
    }
}
