<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table;

use Qant\ORM\Mapper;
use Qant\ORM\Entity;
use Qant\ORM\Mapper\Exception;
use Zend\Db\Sql\Ddl\Column\ColumnInterface;
use Zend\Db\Sql\Ddl\Constraint\ConstraintInterface;

class Table implements TableInterface
{
    use Mapper\Reference\ReferenceMapContract;

    /**
     * tableName
     *
     * @var mixed
     */
    protected $tableName = null;

    /**
     * entityName
     *
     * @var mixed
     */
    protected $entityName = null;

    /**
     * entityClass
     *
     * @var mixed
     */
    protected $entityClass = null;

    /**
     * columns
     *
     * @var mixed
     */
    protected $columns = [];

    /**
     * constraints
     *
     * @var mixed
     */
    protected $constraints = [];

    /**
     * references
     *
     * @var mixed
     */
    protected $references = [];

    /**
     * __construct
     *
     * @param string $_table
     * @param string $_entity
     * @param array $_columns
     * @param array $_constraints
     * @param array $_references
     */
    public function __construct(
        string $_table,
        string $_entityName,
        array $_columns = [],
        array $_constraints = [],
        array $_references = []
    )
    {
        $this->tableName = $_table;
        $this->entityName = $_entityName;
        $this->columns = $_columns;

        foreach ($this->columns as $column) {
            $column->setTable($this);
        }

        $this->constraints = $_constraints;
    }

    /**
     * toArray
     *
     */
    public function toArray() : array
    {
        return [
            'columns' => $this->columns,
            'constraints' => $this->constraints,
            'references' => $this->references,
        ];
    }

    /**
     * setColumn
     *
     * @param string $_columnName
     * @param ColumnInterface $_column
     */
    public function setColumn(string $_columnName, ColumnInterface $_column) : void
    {
        $this->columns[$_columnName] = $_column;
    }

    /**
     * getColumn
     *
     * @param string $_columnName
     */
    public function getColumn(string $_columnName) : Column\ColumnInterface
    {
        if (! isset($this->columns[$_columnName])) {
            throw new Exception\UnknownColumn(vsprintf('Undefined column (%s) in table (%s)', [$_columnName, $this->tableName]));
        }

        return $this->columns[$_columnName];
    }

    /**
     * getColumns
     *
     */
    public function getColumns() : array
    {
        return $this->columns;
    }

    /**
     * setConstraint
     *
     * @param string $_constraintName
     * @param ConstraintInterface $_constraint
     */
    public function setConstraint(string $_constraintName, ConstraintInterface $_constraint) : void
    {
        $this->constraints[$_constraintName] = $_constraint;
    }

    /**
     * setReference
     *
     * @param string $_referenceName
     * @param Mapper\Reference $_reference
     */
    public function setReference(string $_referenceName, Mapper\Reference\Reference $_reference) : void
    {
        $this->references[$_referenceName] = $_reference;
    }

    /**
     * getReference
     *
     * @param string $_referenceName
     */
    public function getReference(string $_referenceName) : Mapper\Reference\Reference
    {
        if (! isset($this->references[$_referenceName])) {
            throw new Exception\UnknownReference(vsprintf('Undefined reference (%s) in table (%s)', [$_referenceName, $this->tableName]));
        }

        return $this->references[$_referenceName];
    }

    /**
     * getReferences
     *
     */
    public function getReferences() : array
    {
        return $this->references;
    }

    /**
     * getTableName
     *
     */
    public function getTableName() : string
    {
        return $this->tableName;
    }

    /**
     * getEntityName
     *
     */
    public function getEntityName() : string
    {
        return $this->entityName;
    }

    /**
     * setEntityClass
     *
     * @param string $_entityClass
     */
    public function setEntityClass(string $_entityClass) : void
    {
        if (! is_subclass_of($_entityClass, Entity\EntityInterface::class)) {
            throw new Exception\UnknownTable(vsprintf('Entity class (%s) must be instance of (%s) class', [$_entityClass, Entity\EntityInterface::class]));
        }

        $this->entityClass = $_entityClass;
    }

    /**
     * getEntityClass
     *
     */
    public function getEntityClass() : ?string
    {
        return $this->entityClass ?? Entity\Entity::class;
    }

}
