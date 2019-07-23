<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Ddl\Column\Timestamp as ZendTimestamp;

class Timestamp extends ZendTimestamp implements ColumnInterface
{
    use ColumnContract;
    use Mapper\Reference\ReferenceMapContract;

    /**
     * @param  null|string|int $default
     * @return self Provides a fluent interface
     */
    public function setDefault($default)
    {
        // - Fix Mysql behavior
        if ($default === true || $default === 'CURRENT_TIMESTAMP') {
            $this->default = new Expression('CURRENT_TIMESTAMP');
        } elseif ($default === '0000-00-00 00:00:00') {
            $this->default = null;
        } else {
            $this->default = $default;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getExpressionData()
    {
        $spec = $this->specification;

        $params   = [];
        $params[] = $this->name;
        $params[] = $this->type;

        $types = [self::TYPE_IDENTIFIER, self::TYPE_LITERAL];

        if (! $this->isNullable) {
            $spec .= ' NOT NULL';
        } else {
            $spec .= ' NULL';
        }

        if ($this->default !== null) {
            $spec    .= ' DEFAULT %s';
            $params[] = $this->default;
            $types[]  = self::TYPE_VALUE;
        }

        $options = $this->getOptions();

        if (isset($options['on_update'])) {
            $spec    .= ' %s';
            $params[] = 'ON UPDATE CURRENT_TIMESTAMP';
            $types[]  = self::TYPE_LITERAL;
        }

        $data = [[
            $spec,
            $params,
            $types,
        ]];

        foreach ($this->constraints as $constraint) {
            $data[] = ' ';
            $data = array_merge($data, $constraint->getExpressionData());
        }

        return $data;
    }
}
