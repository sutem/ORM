<?php
declare(strict_types=1);

namespace Qant\ORM\Mapper\Table;

use Qant\ORM\Mapper;
use Qant\ORM\Mapper\Reference\ReferenceMapInterface;
use Zend\Db\Sql\Ddl\Column\ColumnInterface;
use Zend\Db\Sql\Ddl\Constraint\ConstraintInterface;

interface TableInterface extends ReferenceMapInterface
{
    /**
     * setColumn
     *
     * @param string $_columnName
     * @param ColumnInterface $_column
     */
    public function setColumn(string $_columnName, ColumnInterface $_column) : void;

    /**
     * getColumn
     *
     */
    public function getColumn(string $_columnName) : ?Column\ColumnInterface;

    /**
     * setReference
     *
     * @param string $_referenceName
     * @param Mapper\Reference $_reference
     */
    public function setReference(string $_referenceName, Mapper\Reference\Reference $_reference) : void;

    /**
     * getReference
     *
     * @param string $_referenceName
     */
    public function getReference(string $_referenceName) : Mapper\Reference\Reference;

    /**
     * getTableName
     *
     */
    public function getTableName() : string;
}
