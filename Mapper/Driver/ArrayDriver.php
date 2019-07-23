<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper\Driver;

use Qant\ORM\Mapper;
use Qant\ORM\Mapper\Table;
use Zend\Stdlib\ArrayUtils;
use Zend\Db\Sql\Ddl\Column as ZendColumn;
use Zend\Db\Sql\Ddl\Constraint as ZendConstraint;

/**
 * Class: ArrayDriver
 *
 * @see DriverInterface
 */
class ArrayDriver implements DriverInterface
{
    /**
     * config
     *
     * @var mixed
     */
    protected $config = [];

    /**
     * mapper
     *
     * @var mixed
     */
    protected $mapper = null;

    /**
     * structure
     *
     * @var mixed
     */
    protected $structure = [];

    /**
     * __construct
     *
     * @param array $_config
     */
    public function __construct(array $_config)
    {
        $this->config = $_config;
    }

    /**
     * setMapper
     *
     * @param Mapper $_mapper
     */
    public function setMapper(Mapper\Mapper $_mapper) : void
    {
        $this->mapper = $_mapper;
    }

    /**
     * getMetadata
     *
     */
    public function getMetadata() : Mapper\Metadata
    {

    }

    /**
     * prepareTables
     *
     */
    public function prepareTables() : void
    {
        $structure = [];

        foreach ($this->config['metadata']['tables'] as $tableName => $tableStructure) {

            $dbTableName = $this->mapper->convertEntityNameToDBName(
                $this->mapper->getEntityName($tableName)
            );

            $structure[$tableName] = new Table\Table(
                $dbTableName,
                $this->mapper->getEntityName($tableName),
                $this->getColumns($tableStructure['columns']),
                $this->getConstraints($tableStructure['constraints'] ?? [])
            );

            if (isset($tableStructure['entity'])) {
                if (! class_exists($tableStructure['entity'])) {
                    throw new Mapper\Exception\UnknownTable(vsprintf('Undefined class of entity (%s) on table (%s)', [$tableStructure['entity'], $tableName]));
                }

                $structure[$tableName]->setEntityClass($tableStructure['entity']);
            }
        }

        $this->mapper->setStructure($structure);
    }

    /**
     * prepareReferences
     *
     */
    public function prepareReferences() : void
    {
        foreach ($this->config['metadata']['references'] as $reference => $referenceOptions) {

            $referenceHash = md5($reference);

            $matches = [];
            if (! preg_match('/(!)?([a-z0-9_\-\:]+)(?:@([a-z0-9_\-]+))?>(!)?([a-z0-9_\-\:]+)(?:@([a-z0-9_\-]+))?/i', str_replace(' ', '', $reference), $matches)) {
                throw new Mapper\Exception\UnknownReference(vsprintf('Unknown syntax of reference declare: %s', [$reference]));
            }

            array_shift($matches);
            list($strict1, $table1, $alias1, $strict2, $table2, $alias2) = ArrayUtils::merge(array_fill(0, 6, ''), $matches, true);

            $strict1 = (bool)$strict1;
            $strict2 = (bool)$strict2;

            $table1 = $this->mapper->getTable($table1);
            $table2 = $this->mapper->getTable($table2);

            if (! preg_match('/([a-z0-9_\-\:]+)\(([a-z0-9_\-\,]+)\)?/i', str_replace(' ', '', $referenceOptions['via']), $matches)) {
                throw new Mapper\Exception\UnknownReference(vsprintf('Unknown "via" (%s) syntax of reference %s ', [$referenceOptions['via'], $reference]));
            }

            array_shift($matches);
            list($viaTable, $columns) = $matches;

            $viaTable = $this->mapper->getTable($viaTable);

            $columns = explode(',', $columns);

            foreach ($columns as $column) {
                if (! $viaTable->getColumn($column)) {
                    throw new Mapper\Exception\UnknownReference(
                        vsprintf(
                            'Unknown column (%s) in "via" table (%s) in reference %s',
                            [$column, $viaTable->getTableName(), $reference]
                        )
                    );
                }
            }

            if (in_array((int)$referenceOptions['type'], [Mapper\Reference\Reference::O2O, Mapper\Reference\Reference::O2M])) {

                if ($viaTable != $table2) {
                    throw new Mapper\Exception\UnknownReference(
                        vsprintf(
                            'On reference One-To-Many or One-To-One, "via table" (%s) should be equal to table2 (%s)',
                            [$column, $viaTable->getTableName(), $reference]
                        )
                    );
                }

                if (! $column = $viaTable->getColumn(array_shift($columns))) {
                    throw new Mapper\Exception\UnknownReference(
                        vsprintf(
                            'Undefined column name (%s) in "via table" (%s) in reference %s',
                            [$column, $viaTable->getTableName(), $reference]
                        )
                    );
                }

                $referenceName = $alias2 ?: $this->mapper->getEntityName($table2->getTableName());
                $table1->setReference($referenceName, new Mapper\Reference\Reference(
                    $referenceHash,
                    $referenceName,
                    $table1($column),
                    (int)$referenceOptions['type'] === Mapper\Reference\Reference::O2O
                        ? Mapper\Reference\Reference::TOONE
                        : Mapper\Reference\Reference::TOMANY,
                    $this->prepareConditions($referenceOptions['conditions'], [$table1, $table2]) ?? [],
                    $strict2
                ));

                $referenceName = $alias1 ?: $this->mapper->getEntityName($table1->getTableName());
                $table2->setReference($referenceName, new Mapper\Reference\Reference(
                    $referenceHash,
                    $referenceName,
                    $column($table1),
                    Mapper\Reference\Reference::TOONE,
                    $this->prepareConditions($referenceOptions['conditions'], [$table1, $table2]) ?? [],
                    $strict1
                ));

            } elseif ((int)$referenceOptions['type'] === Mapper\Reference\Reference::M2M) {

                list($column1, $column2) = $columns;

                if (! $column1 = $viaTable->getColumn($column1)) {
                    throw new Mapper\Exception\UnknownReference(
                        vsprintf(
                            'Undefined column name (%s) in "via table" (%s) in reference %s',
                            [$column1, $viaTable->getTableName(), $reference]
                        )
                    );
                }

                if (! $column2 = $viaTable->getColumn($column2)) {
                    throw new Mapper\Exception\UnknownReference(
                        vsprintf(
                            'Undefined column name (%s) in "via table" (%s) in reference %s',
                            [$column2, $viaTable->getTableName(), $reference]
                        )
                    );
                }

                $referenceTables = [$table1, $table2, $column1->getTable(), $column2->getTable()];

                $referenceName = $alias2 ?:  $this->mapper->getEntityName($table2->getTableName());
                $table1->setReference($referenceName, new Mapper\Reference\Reference(
                    $referenceHash,
                    $referenceName,
                    $table1($column1($column2($table2))),
                    Mapper\Reference\Reference::TOMANY,
                    $this->prepareConditions($referenceOptions['conditions'], $referenceTables) ?? [],
                    $strict2
                ));

                $referenceName = $alias1 ?:  $this->mapper->getEntityName($table1->getTableName());
                $table2->setReference($referenceName, new Mapper\Reference\Reference(
                    $referenceHash,
                    $referenceName,
                    $table2($column2($column1($table1))),
                    Mapper\Reference\Reference::TOMANY,
                    $this->prepareConditions($referenceOptions['conditions'], $referenceTables) ?? [],
                    $strict1
                ));
            }
        }
    }

    /**
     * prepareConditions
     *
     * @param array $_conditions
     */
    protected function prepareConditions(array $_conditions, array $_availableTables) : array
    {
        $return = [];
        foreach ($_conditions as $conditionName => $conditionValue) {

            if (! stripos($conditionName, '.')) {
                throw new Mapper\Exception\UnknownReference(
                    vsprintf('Unknown syntax at condition name (%s)', [$conditionName])
                );
            }

            list($table, $column) = explode('.', $conditionName);
            $table = $this->mapper->getTable($table);

            if (! in_array($table, $_availableTables)) {
                throw new Mapper\Exception\UnknownReference(
                    vsprintf('Unknown table (%s) of condition %s', [$table->getEntityName(), $conditionName])
                );
            }

            if (stripos((string)$conditionValue, '.')) {
                list($tableValue, $columnValue) = explode('.', $conditionValue);
                if ($tableValue = $this->mapper->has($tableValue)) {
                    $conditionValue = $tableValue->getColumn($columnValue);
                }
            }

            if (! isset($return[$table->getEntityName()])) {
                $return[$table->getEntityName()] = [];
            }

            $return[$table->getEntityName()][] = [$table->getColumn($column), $conditionValue];
        }

        return $return;
    }

    /**
     * getColumns
     *
     * @param array $_columns
     */
    protected function getColumns(array $_columns) : array
    {
        $return = [];

        # - ID column
        $return['id'] = $this->getColumn([
            'name' => 'id',
            'type' => Table\Column\Integer::class,
            'null' => false,
        ])
            ->setOption('auto_increment', true)
            ->setOption('unsigned', true);

        # - Insert ID column
        $return['__idinsert'] = $this->getColumn([
            'name' => '__idinsert',
            'type' => Table\Column\Varchar::class,
            'length' => 64,
            'null' => true,
        ]);

        foreach ($_columns as $columnName => $column) {
            $column = array_merge(['name' => $columnName, 'alias' => $columnName], $column);
            $return[$columnName] = $this->getColumn($column);
        }

        return $return;
    }

    /**
     * getColumn
     *
     * @param array $_column
     */
    protected function getColumn(array $_column) : ZendColumn\ColumnInterface
    {
        $_column = array_merge([
            'null' => false,
            'length' => null,
            'default' => null,
        ], $_column);

        if (is_subclass_of($_column['type'], ZendColumn\AbstractPrecisionColumn::class)) {
            $params = $_column['length'] !== null ? explode(',', (string)$_column['length'], 2) : [null, null];
            $return = new $_column['type']($_column['name'], $params[0], $params[1] ?? null, $_column['null'], $_column['default']);
        } elseif (is_subclass_of($_column['type'], ZendColumn\AbstractLengthColumn::class)) {
            $return = new $_column['type']($_column['name'], $_column['length'], $_column['null'], $_column['default']);
        } else {
            $return = new $_column['type']($_column['name'], $_column['null'], $_column['default']);
        }

        if (isset($_column['alias'])) {
            $return->setAlias($_column['alias']);
        }

        return $return;
    }

    /**
     * getConstraints
     *
     * @param array $_constraints
     */
    protected function getConstraints(array $_constraints) : array
    {
        $return = [];

        $return['PRIMARY'] = new Table\Constraint\PrimaryKey('id', 'PRIMARY');
        $return['__idinsert'] = new Table\Constraint\Index('__idinsert', '__idinsert');

        foreach ($_constraints as $constraintName => $constraint) {
            $constraint['name'] = $constraintName;
            $return[$constraintName] = $this->getConstraint($constraint);
        }

        return $return;
    }

    /**
     * getConstraint
     *
     * @param array $_constraint
     */
    protected function getConstraint(array $_constraint) : ZendConstraint\ConstraintInterface
    {
        $_constraint = array_merge(['type' => Table\Constraint\Index::class], $_constraint);
        return new $_constraint['type']($_constraint['columns'], $_constraint['name']);
    }
}
