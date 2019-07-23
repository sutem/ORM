<?php

declare(strict_types=1);

namespace Qant\ORM\Sql;

use Zend\Db\Sql\Insert as ZendInsert;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Adapter\Platform\PlatformInterface;

class Insert extends ZendInsert
{
    const VALUES_MULTI = 'multi';

    /**
     * values
     *
     * @var mixed
     */
    protected $values = [];

    /**
     * onConflictColumns
     *
     * @var mixed
     */
    protected $onConflictColumns = null;

    /**
     * values
     *
     */
    public function values($_values, $_flag = self::VALUES_MULTI)
    {
        if ($_flag !== static::VALUES_MULTI) {
            return parent::values($_values, $_flag);
        }

        $columns = [];
        foreach ($_values as $value) {
            $columns = array_unique(array_merge($columns, array_keys($value)));
        }

        $this->columns = $columns;
        foreach ($_values as $value) {
            foreach ($columns as $column) {
                $value[$column] = $value[$column] ?? null;
            }
            $this->values[] = $value;
        }

        return $this;
    }

    /**
     * onConflict
     *
     * @param array $_columns
     */
    public function onConflict(array $_columns = null)
    {
        $this->onConflictColumns = $_columns;
        return $this;
    }

    /**
     * processInsert
     *
     * @param PlatformInterface $platform
     * @param DriverInterface $driver
     * @param ParameterContainer $parameterContainer
     */
    protected function processInsert(
        PlatformInterface $platform,
        DriverInterface $driver = null,
        ParameterContainer $parameterContainer = null
    ) {
        if ($this->select) {
            return;
        }

        if (! $this->values) {
            return parent::processInsert($platform, $driver, $parameterContainer);
        }

        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $platform->quoteIdentifier($column);
        }

        $i = 0; $values = [];
        foreach ($this->values as $value) {
            $rowValue = [];
            foreach ($this->columns as $column) {
                $param = 'c_' . $i++;
                $parameterContainer->offsetSet($param, $value[$column]);
                $rowValue[] = $driver->formatParameterName($param);
            }
            $values[] = '(' . implode(',', $rowValue) . ')';
        }

        $return = sprintf(
            $this->getSpecification($platform),
            $this->resolveTable($this->table, $platform, $driver, $parameterContainer),
            implode(', ', $columns),
            implode(', ', $values)
        );

        return $return;
    }

    /**
     * Simple test for an associative array
     *
     * @link http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
     * @param array $array
     * @return bool
     */
    protected function isAssocative(array $array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * getSpecification
     *
     */
    protected function getSpecification(PlatformInterface $_platform) : string
    {
        return 'INSERT INTO %1$s (%2$s) VALUES %3$s' . $this->getOnConflictSpecification($_platform);
    }

    /**
     * getOnConflictSpecification
     *
     */
    protected function getOnConflictSpecification(PlatformInterface $_platform) : string
    {
        if (! $this->onConflictColumns) {
            return '';
        }

        $columns = [];
        foreach ($this->onConflictColumns as $column) {
            $columns[] = $_platform->quoteIdentifier($column) . '=VALUES(' . $_platform->quoteIdentifier($column) . ')';
        }

        return sprintf(' ON DUPLICATE KEY UPDATE %s', implode(', ', $columns));
    }
}
