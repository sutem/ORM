<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Qant\ORM\ModelManagerInterface;
use Qant\ORM\Mapper;
use Qant\ORM\Mapper\Table;
use Qant\ORM\Mapper\Reference;
use Qant\ORM\Entity\EntityInterface;
use Qant\Collection\Collection;
use Zend\Db\Sql\Join;
use Zend\Db\Sql\Predicate;

class ReferenceProcessor extends BaseProcessor implements ReferenceProcessorInterface
{
    /**
     * reference
     *
     * @var mixed
     */
    protected $reference = null;

    /**
     * repository
     *
     * @var mixed
     */
    protected $repository = null;

    /**
     * parentProcessor
     *
     * @var mixed
     */
    protected $parentProcessor = null;

    /**
     * gateway
     *
     * @var mixed
     */
    protected $gateway = null;

    /**
     * referenceKeysMap
     *
     * @var mixed
     */
    protected $referenceKeysMap = [];

    /**
     * unqiuePreffix
     *
     * @var mixed
     */
    protected $unqiuePreffix = null;

    /**
     * unlinkedKeysMap
     *
     * @var mixed
     */
    protected $unlinkedKeysMap = [];

    /**
     * __construct
     *
     * @param Mapper\Reference\ReferenceInterface $_reference
     * @param ProcessorRepository $_repository
     * @param ReferenceProcessorInterface $_parentProcessor
     * @param GatewayInterface $_gateway
     * @param ModelManagerInterface $_mm
     */
    public function __construct(
        Mapper\Reference\ReferenceInterface $_reference,
        ProcessorRepository $_repository = null,
        ReferenceProcessorInterface $_parentProcessor,
        GatewayInterface $_gateway,
        ModelManagerInterface $_mm
    )
    {
        $this->reference = $_reference;
        $this->parentProcessor = $_parentProcessor;
        $this->gateway = $_gateway;
        $this->mm = $_mm;
        $this->repository = $_repository ?? $this->mm->getProcessorRepository(
            $this->getTargetTable()->getEntityName()
        );
        $this->uniquePreffix = uniqid();
    }

    /**
     * setRepository
     *
     * @param Gateway\Repository $_repository
     */
    public function setRepository(ProcessorRepositoryInterface $_repository)
    {
        $this->repository = $_repository;
    }

    /**
     * getRepository
     *
     */
    public function getRepository() : ProcessorRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * prepareSql
     *
     */
    public function prepareSelect() : void
    {
        $select = $this->gateway->select();
        $referenceMap = $this->reference->getReferenceMap();
        $conditions = $this->reference->getConditions();

        $currentColumn = array_shift($referenceMap);
        $currentTableAlias = $this->gateway->getProcessor()->getTableAlias();

        $targetColumn = array_pop($referenceMap);
        $targetTable = $targetColumn->getTable();

        if ($referenceMap) {

            reset($referenceMap);
            while ($column = current($referenceMap)) {

                $columnTable = $column->getTable();
                $tableAlias = $this->prepareSelectTable($columnTable);

                $predicates = [
                    new Predicate\Operator(
                        vsprintf('%s.%s', [ $currentTableAlias, $currentColumn->getName() ]),
                        '=',
                        vsprintf('%s.%s', [ $tableAlias, $column->getName() ]),
                        Predicate\Operator::TYPE_IDENTIFIER,
                        Predicate\Operator::TYPE_IDENTIFIER
                    )
                ];

                if (isset($conditions[$columnTable->getEntityName()])) {
                    foreach ($conditions[$columnTable->getEntityName()] as $condition) {
                        if (is_object($condition[1]) && $condition[1] instanceof Table\Column\ColumnInterface) {
                            $predicates[] = new Predicate\Operator(
                                vsprintf('%s.%s', [$this->prepareSelectTable($condition[0]->getTable()), $condition[0]->getName()]),
                                '=',
                                vsprintf('%s.%s', [$this->prepareSelectTable($condition[1]->getTable), $condition[1]->getName()]),
                                Predicate\Operator::TYPE_IDENTIFIER,
                                Predicate\Operator::TYPE_IDENTIFIER
                            );
                        } else {
                            $predicates[] = new Predicate\Operator(
                                vsprintf('%s.%s', [$this->prepareSelectTable($condition[0]->getTable()), $condition[0]->getName()]),
                                '=',
                                vsprintf('%s', [$condition[1]]),
                                Predicate\Operator::TYPE_IDENTIFIER,
                                Predicate\Operator::TYPE_VALUE
                            );
                        }
                    }
                }

                $predicateSet = new Predicate\Predicate();
                $predicateSet->addPredicates($predicates, $predicateSet::OP_AND);

                $select->join(
                    [$tableAlias => $columnTable->getTableName()],
                    $predicateSet,
                    $this->prepareSelectColumns($columnTable),
                    Join::JOIN_LEFT
                );

                # - If the tables of the current and next fields are identical, then go to the next field.
                if (($nextColumn = next($referenceMap)) && $nextColumn->getTable() == $columnTable) {
                    $currentColumn = $nextColumn;
                    next($referenceMap);
                # - If the tables of the current and next fields aren't identical, then use standart column to reference and go back.
                } elseif ($nextColumn) {
                    $currentColumn = prev($referenceMap)->getTable()->getColumn('id');
                }

                $currentTableAlias = $this->prepareSelectTable($currentColumn->getTable());
            }
        }

        # - Join target table
        $tableAlias = $this->prepareSelectTable($targetTable);
        $predicates = [
            new Predicate\Operator(
                vsprintf('%s.%s', [$currentTableAlias, $currentColumn->getName()]),
                '=',
                vsprintf('%s.%s', [$tableAlias, $targetColumn->getName()]),
                Predicate\Operator::TYPE_IDENTIFIER,
                Predicate\Operator::TYPE_IDENTIFIER
            )
        ];

        if (isset($conditions[$targetTable->getEntityName()])) {
            foreach ($conditions[$targetTable->getEntityName()] as $condition) {
                if (is_object($condition[1]) && $condition[1] instanceof Table\Column\ColumnInterface) {
                    $predicates[] = new Predicate\Operator(
                        vsprintf('%s.%s', [$this->prepareSelectTable($condition[0]->getTable()), $condition[0]->getName()]),
                        '=',
                        vsprintf('%s.%s', [$this->prepareSelectTable($condition[1]->getTable), $condition[1]->getName()]),
                        Predicate\Operator::TYPE_IDENTIFIER,
                        Predicate\Operator::TYPE_IDENTIFIER
                    );
                } else {
                    $predicates[] = new Predicate\Operator(
                        vsprintf('%s.%s', [$this->prepareSelectTable($condition[0]->getTable()), $condition[0]->getName()]),
                        '=',
                        vsprintf('%s', [$condition[1]]),
                        Predicate\Operator::TYPE_IDENTIFIER,
                        Predicate\Operator::TYPE_VALUE
                    );
                }
            }
        }

        $predicateSet = new Predicate\Predicate();
        $predicateSet->addPredicates($predicates, $predicateSet::OP_AND);

        $select->join(
            [$tableAlias => $targetTable->getTableName()],
            $predicateSet,
            $this->prepareSelectColumns($targetTable),
            Join::JOIN_LEFT
        );
    }

    /**
     * parseResult
     *
     * @param array $_row
     */
    public function parseResult(array &$_row) : void
    {
        $referenceMap = $this->reference->getReferenceMap();

        if (count($referenceMap) === 2) {
            $targetColumn = $referenceMap[1];
            $targetColumnAlias = $this->prepareSelectColumnAlias($targetColumn);
            $referenceColumnAlias = $this->parentProcessor->prepareSelectColumnAlias(
                $referenceMap[0]->getTable()->getColumn('id')
            );
        } elseif(count($referenceMap) === 4) {
            $targetColumn = $referenceMap[3];
            $targetColumnAlias = $this->prepareSelectColumnAlias($targetColumn);
            $referenceColumnAlias = $this->prepareSelectColumnAlias($referenceMap[1]);
        }

        if (! $_row[$referenceColumnAlias] || ! $_row[$targetColumnAlias]) {
            return;
        }

        $columns = $this->prepareSelectColumns($targetColumn->getTable());
        foreach ($columns as $columnAlias => $columnName) {
            $entity[$columnName] = $_row[$columnAlias];
        }

        $entity = $this->setEntityToRepository($entity);
        $this->setReferenceKeysMap((int)$_row[$referenceColumnAlias], $entity);
    }

    /**
     * compareEntity
     *
     */
    public function compareEntity(EntityInterface $_entity) : void
    {
        $relatedEntities = [];
        if ($relatedEntities = $this->getRelatedEntities($_entity)) {
            $this->gateway->compareEntities($relatedEntities);
        }

        $_entity->setRelatedEntities($this->reference->getReferenceName(), $this->reference->getReferenceType() === $this->reference::TOONE ? array_shift($relatedEntities) : new Collection($relatedEntities));
    }

    /**
     * getTargetTable
     *
     */
    public function getTargetTable() : Table\Table
    {
        $referenceMap = $this->reference->getReferenceMap();
        return array_pop($referenceMap)->getTable();
    }

    /**
     * getTableAlias
     *
     */
    public function getTableAlias() : string
    {
        $referenceMap = $this->reference->getReferenceMap();
        return $this->prepareSelectTable(array_pop($referenceMap)->getTable());
    }

    /**
     * getTableName
     *
     */
    public function getTableName() : string
    {
        return $this->getTargetTable()->getTableName();
    }

    /**
     * getColumns
     *
     */
    public function getColumns() : array
    {
        return $this->getTargetTable()->getColumns();
    }

    /**
     * getTableReferences
     *
     */
    public function getTableReferences() : array
    {
        return $this->getTargetTable()->getReferences();
    }

    /**
     * getReference
     *
     */
    public function getReference($_reference) : Reference\ReferenceInterface
    {
        return $this->getTargetTable()->getReference($_reference);
    }

    /**
     * setEntitiesToRepository
     *
     * @param array $entities
     */
    public function setEntitiesToRepository(array $_entities) : void
    {
        foreach ($_entities as $referenceId => $referencedEntities) {
            foreach ($referencedEntities as $entity) {
                $entity = $this->setEntityToRepository($entity);
                $this->setReferenceKeysMap($referenceId, $entity);
            }
        }
    }

    /**
     * unlinkEntities
     *
     * @param array $_unlinkedEntities
     */
    public function unlinkEntities(array $_unlinkedEntities) : void
    {
        $this->unlinkedKeysMap = $_unlinkedEntities;
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
     * setReferenceKeysMap
     *
     * @param mixed $_reference
     * @param mixed $_entity
     */
    protected function setReferenceKeysMap($_reference, EntityInterface $_entity) : void
    {
        if (! isset($this->referenceKeysMap[$_reference])) {
            $this->referenceKeysMap[$_reference] = [];
        }

        if (! in_array($_entity, $this->referenceKeysMap[$_reference])) {
            $this->referenceKeysMap[$_reference][] = $_entity;
        }
    }

    /**
     * getRelatedEntities
     *
     * @param mixed $_entity
     */
    protected function getRelatedEntities($_entity) : array
    {
        $return = [];

        if (isset($this->referenceKeysMap[$_entity->id])) {
            foreach ($this->referenceKeysMap[$_entity->id] as $entity) {
                $return[$entity->id] = $entity;
            }
        }

        return $return;
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
