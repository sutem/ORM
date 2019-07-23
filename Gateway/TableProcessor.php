<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Qant\ORM\ModelManagerInterface;
use Qant\ORM\Mapper;
use Qant\ORM\Mapper\Table;
use Qant\ORM\Mapper\Reference;

class TableProcessor extends BaseProcessor implements TableProcessorInterface, ReferenceProcessorInterface
{
    /**
     * table
     *
     * @var mixed
     */
    protected $table = null;

    /**
     * repository
     *
     * @var mixed
     */
    protected $repository = null;

    /**
     * gateway
     *
     * @var mixed
     */
    protected $gateway = null;

    /**
     * unqiuePreffix
     *
     * @var mixed
     */
    protected $unqiuePreffix = null;

    /**
     * __construct
     *
     * @param Mapper\Reference\ReferenceInstance $_reference
     * @param ProcessorRepository $_repository
     * @param Gateway $_gateway
     */
    public function __construct(
        Table\TableInterface $_table,
        ProcessorRepository $_repository,
        GatewayInterface $_gateway,
        ModelManagerInterface $_mm
    )
    {
        $this->table = $_table;
        $this->repository = $_repository;
        $this->gateway = $_gateway;
        $this->mm = $_mm;
        $this->uniquePreffix = uniqid();
    }

    /**
     * getReference
     *
     */
    public function getReference($_reference) : Reference\ReferenceInterface
    {
        return $this->table->getReference($_reference);
    }

    /**
     * getTargetTable
     *
     */
    public function getTargetTable()
    {
        return $this->table;
    }

    /**
     * getTableName
     *
     */
    public function getTableName() : string
    {
        return $this->table->getTableName();
    }

    /**
     * getEntityName
     *
     */
    public function getEntityName() : string
    {
        return $this->table->getEntityName();
    }

    /**
     * getTableAlias
     *
     */
    public function getTableAlias() : string
    {
        return $this->prepareSelectTable($this->table);
    }

    /**
     * getTableReferences
     *
     */
    public function getTableReferences() : array
    {
        return $this->table->getReferences();
    }

    /**
     * getColumns
     *
     */
    public function getColumns()
    {
        return $this->table->getColumns();
    }

    /**
     * prepareSelect
     *
     */
    public function prepareSelect() : void
    {
        $select = $this->gateway->select()
            ->columns($this->prepareSelectColumns($this->table));

        if (! $select->isTableReadOnly()) {
            $select->from([$this->prepareSelectTable($this->table) => $this->getTableName()]);
        }
    }

    /**
     * parseResult
     *
     * @param array $_row
     */
    public function parseResult(array &$_row) : void
    {
        # - Get this columns
        $columns = $this->table->getColumns();

        foreach ($columns as $column) {
            $entity[$column->getAlias()] = $_row[$this->prepareSelectColumnAlias($column)];
        }

        $this->setEntityToRepository($entity);
    }

    /**
     * prepareSelectColumnAlias
     *
     * @param Table\Column\ColumnInterface $_column
     */
    public function prepareSelectColumnAlias(Table\Column\ColumnInterface $_column) : string
    {
        return $this->prepareSelectTable($_column->getTable()) . '.' . $_column->getName();
    }

    /**
     * prepareSelectColumns
     *
     * @param Table\Table $_table
     */
    protected function prepareSelectColumns(Table\Table $_table, array $_columns = []) : array
    {
        $columns = $_table->getColumns();
        if ($_columns) {
            $columns = array_filter($columns, function($_column) use ($_columns) {
                return in_array($_column->getName(), $_columns);
            });
        }

        $return = [];
        foreach ($columns as $column) {
            $return[$this->prepareSelectColumnAlias($column)] = $column->getName();
        }

        return $return;
    }

    /**
     * prepareSelectTable
     *
     * @param Table\Table $_table
     */
    protected function prepareSelectTable(Table\Table $_table) : string
    {
        return $this->uniquePreffix . ':' . $_table->getTableName();
    }

}
