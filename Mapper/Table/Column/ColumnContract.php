<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Table\Column;

use Qant\ORM\Mapper;

trait ColumnContract
{
    /**
     * table
     *
     * @var Table\Table
     */
    protected $table = null;

    /**
     * alias
     *
     * @var mixed
     */
    protected $alias = null;

    /**
     * setTable
     *
     */
	public function setTable(Mapper\Table\Table $_table) : void
    {
        $this->table = $_table;
	}

    /**
     * getTable
     *
     */
    public function getTable() : Mapper\Table\Table
    {
        return $this->table;
    }

    /**
     * setAlias
     *
     * @param string $_alias
     */
    public function setAlias(string $_alias) : void
    {
        $this->alias = $_alias;
    }

    /**
     * getAlias
     *
     */
    public function getAlias() : string
    {
        return $this->alias ?? $this->getName();
    }

    /**
     * __clone
     *
     */
    public function __clone()
    {
        # - prepare for Engineer compare
        $this->table = null;
        $this->alias = null;
    }

}
