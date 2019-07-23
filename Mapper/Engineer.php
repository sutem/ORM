<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper;

use Qant\Database\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\Sql\Ddl;

class Engineer
{
    /**
     * mapper
     *
     * @var mixed
     */
    protected $mapper = null;

    /**
     * adapter
     *
     * @var mixed
     */
    protected $adapter = null;

    /**
     * modifiedModels
     *
     * @var mixed
     */
    protected $modifiedModels = [];

    /**
     * tables
     *
     * @var mixed
     */
    protected $tables = [];

    /**
     * __construct
     *
     * @param Mapper $_mapper
     */
    public function __construct(Adapter $_adapter)
    {
        $this->adapter = $_adapter;
    }

    /**
     * inspect
     *
     */
    public function inspect(Mapper $_mapper)
    {
        $mapperTables = $_mapper->getStructure();
        $databaseTables = $_mapper->getDatabaseStructure();

        $modifiedModels = [];
        foreach ($mapperTables as $tableStructure) {

            /* Refact this moment */
            $tableName = $tableStructure->getTableName();
            $tableStructure = $tableStructure->toArray();

            $databaseTableStructure = isset($databaseTables[$tableName]) ? $databaseTables[$tableName]->toArray() : [];

            $tableStructure = array_merge(['columns' => [], 'constraints' => []], $this->getNormalizedStructure($tableStructure, $_mapper));
            $dbTableStructure = array_merge(['columns' => [], 'constraints' => []], $databaseTableStructure);

            if (! $this->isDifferentTables($tableStructure, $dbTableStructure)) {
                continue;
            }

            $table = $databaseTableStructure
                ? new Ddl\AlterTable($tableName)
                : new Ddl\CreateTable($tableName);

            $compareObjects = function($_object1, $_object2) {
                $_object1 = clone $_object1;
                $_object2 = clone $_object2;
                return strcmp(md5(serialize($_object1)), md5(serialize($_object2)));
            };

            // - Get new or modified columns
            $diffColumns = array_udiff($tableStructure['columns'], $dbTableStructure['columns'], $compareObjects);
            foreach ($diffColumns as $column) {
                if (isset($dbTableStructure['columns'][$column->getName()])) {
                    $table->changeColumn($column->getName(), $column);
                    unset($dbTableStructure['columns'][$column->getName()]);
                } else {
                    $table->addColumn($column);
                }
            }

            // - Get dropped columns
            $diffColumns = array_udiff($dbTableStructure['columns'], $tableStructure['columns'], $compareObjects);
            foreach ($diffColumns as $column) {
                $table->dropColumn($column->getName());
            }

            // - Get new constraints
            $diffConstraints = array_udiff($tableStructure['constraints'], $dbTableStructure['constraints'], $compareObjects);
            foreach ($diffConstraints as $constraint) {
                if (isset($dbTableStructure['constraints'][$constraint->getName()])) {
                    $table->dropConstraint($constraint->getName());
                    unset($dbTableStructure['constraints'][$column->getName()]);
                }
                $table->addConstraint($constraint);
            }

            // - Get dropped constraints
            $diffConstraints = array_udiff($dbTableStructure['constraints'], $tableStructure['constraints'], $compareObjects);
            foreach ($diffConstraints as $constraint) {
                $table->dropConstraint($constraint->getName());
            }

            $modifiedModels[$tableName] = $table;
        }

        $this->modifiedModels = $modifiedModels;
        return $this;
    }

    /**
     * getModifiedModels
     *
     */
    public function getModifiedModels() : array
    {
        return $this->modifiedModels;
    }

    /**
     * applyChanges
     *
     */
    public function applyChanges()
    {
        if ($this->modifiedModels) {
            // - Process ddl statements
            $sqlBuilder = new Sql\Sql($this->adapter);
            foreach ($this->modifiedModels as $key => $table) {
                $this->adapter->query(
                    $sqlBuilder->getSqlStringForSqlObject($table),
                    $this->adapter::QUERY_MODE_EXECUTE
                );
                unset($this->modifiedModels[$key]);
            }
        }
    }

    /**
     * getNormalizedStructure
     *
     * @param array $_tableStructure
     * @param mixed $_mapper
     */
    protected function getNormalizedStructure(array $_tableStructure, $_mapper)
    {
        $tempTableName = uniqid('tempInspectName_');
        $table = new Ddl\CreateTable($tempTableName);

        foreach ($_tableStructure['columns'] as $column) {
            $table->addColumn($column);
        }

        foreach ($_tableStructure['constraints'] as $constraint) {
            $table->addConstraint($constraint);
        }

        $this->adapter->query(
            (new Sql\Sql($this->adapter))->getSqlStringForSqlObject($table),
            $this->adapter::QUERY_MODE_EXECUTE
        );

        $return = [
            'columns' => $_mapper->loadColumns($tempTableName),
            'constraints' => $_mapper->loadConstraints($tempTableName),
        ];

        $table = new Ddl\DropTable($tempTableName);
        $this->adapter->query(
            (new Sql\Sql($this->adapter))->getSqlStringForSqlObject($table),
            $this->adapter::QUERY_MODE_EXECUTE
        );

        return $return;
    }

    /**
     * isDifferentTables
     *
     * @param array $_table1
     * @param array $_table2
     */
    protected function isDifferentTables(array $_table1, array $_table2) : bool
    {
        $table1Columns = array_keys($_table1['columns']);
        sort($table1Columns);
        $table2Columns = array_keys($_table2['columns']);
        sort($table2Columns);

        if ($table1Columns != $table2Columns) {
            return true;
        }

        foreach ($_table1['columns'] as $columnName => $column) {
            if (clone $column != clone $_table2['columns'][$columnName]) {
                return true;
            }
        }

        if ($_table1['constraints'] != $_table2['constraints']) {
            return true;
        }

        return false;
    }
}
