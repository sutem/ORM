<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Qant\ORM\Entity;
use Qant\ORM\Sql;
use Zend\Db\Sql\Predicate;

abstract class BaseProcessor
{
    /**
     * mm
     *
     * @var mixed
     */
    protected $mm = null;

    /**
     * insertIdMap
     *
     * @var mixed
     */
    protected $insertIdMap = [];

    /**
     * setGateway
     *
     * @param Gateway\GatewayInterface $_gateway
     */
    public function setGateway(GatewayInterface $_gateway) : void
    {
        $this->gateway = $_gateway;
    }

    /**
     * getGateway
     *
     */
    public function getGateway() : GatewayInterface
    {
        return $this->gateway;
    }

    /**
     * saveEntities
     *
     */
    public function saveEntities() : void
    {
        if (! $entities = $this->repository->getAll()) {
            return;
        }

        $tableGateway = $this->gateway->getTableGateway($this->getTableName());
        $tableReferences = $this->getTableReferences();

        $createdEntitiesIdentifiers = [];
        $updatedEntitiesIdentifiers = [];

        # - Prepare entities and detect update/insert ids
        foreach ($entities as $entity) {
            if (is_string($entity->id) && substr($entity->id, 0, 3) === 'new') {
                $createdEntitiesIdentifiers[] = $entity->id;
                $entity->__idinsert = $entity->id;
                $entity->id = null;
                continue;
            }
            $updatedEntitiesIdentifiers[] = $entity->id;
        }

        # - Load full entity data from DB
        if ($updatedEntitiesIdentifiers) {
            $toUpdateEntities = $tableGateway->select(['id' => $updatedEntitiesIdentifiers]);
            foreach ($toUpdateEntities as $row) {
                $entity = $entities[$row['id']];
                foreach ($this->getColumns() as $column) {
                    if (! isset($entity[$column->getAlias()])) {
                        $entity[$column->getAlias()] = $row[$column->getName()];
                    }
                }
            }
        }

        # - Prepare entities for Insert/Update with Insert-UODK
        $rowSet = $eventCreateResponses = $eventSaveResponses = [];

        foreach ($entities as $entity) {

            // - Create params array for emit events
            $params = [ 'persist' => is_null($entity->id), 'mm' => $this->mm ];
            $params = [ 'persist' => is_null($entity->id), 'mm' => $this->mm ];

            // - Emit entity create event
            if (is_null($entity->id)) {
                $eventCreateResponses[$entity->__idinsert] = $this->mm->getEventManager()->trigger(
                    $this->getTargetTable()->getEntityClass()::getEventName('create', 'before'),
                    $entity,
                    $params
                );

                if ($eventCreateResponses[$entity->__idinsert]->contains(false)) {
                    continue;
                }
            }

            // - Emit entity save event
            $eventSaveResponses[$entity->id] = $this->mm->getEventManager()->trigger(
                $this->getTargetTable()->getEntityClass()::getEventName('save', 'before'),
                $entity,
                $params
            );

            if ($eventSaveResponses[$entity->id]->contains(false)) {
                continue;
            }

            $row = [];
            foreach ($this->getColumns() as $column) {
                $row[$column->getName()] = $entity[$column->getAlias()] ?? null;
            }
            $rowSet[] = $row;
        }

        # - Prepare UODK expression
        $onConflictColumns = [];
        foreach ($this->getColumns() as $column) {
            if ($column->getName() === 'id') {
                continue;
            }
            $onConflictColumns[] = $column->getName();
        }

        # - Insert/Update entities
        $tableGateway->insert($rowSet, $onConflictColumns);

        # - Prepare predicates for last inserted entities
        $predicates = [];
        if ($createdEntitiesIdentifiers) {
            $predicates['__idinsert'] = $createdEntitiesIdentifiers;
        }

        # - Prepare predicates for last updated entities
        if ($updatedEntitiesIdentifiers) {
            $predicates['id'] = $updatedEntitiesIdentifiers;
        }

        # - Select entities
        $result = $tableGateway->select(function($_select) use ($predicates) {
            $_select->where($predicates, 'OR');
        });

        # - Flush and refill repository
        $this->flushRepository();
        foreach ($result as $entity) {
            $createdEntity = false;
            if (! is_null($entity['__idinsert'])) {
                $this->insertIdMap[$entity['__idinsert']] = $entity['id'];
                $createdEntity = true;
            }
            //TODO: Refact this hack: $entity['__idinsert'] = null // - set to nullify;
            $entity = $this->setEntityToRepository($entity);

            // - Create params array for emit events
            $params = [
                'persist' => $createdEntity,
                'mm' => $this->mm,
            ];

            // - Emit entity create event
            if ($createdEntity) {
                $this->mm->getEventManager()->trigger(
                    $this->getTargetTable()->getEntityClass()::getEventName('create', 'after'),
                    $entity,
                    array_merge($params, [
                        'beforeResponses' => $eventCreateResponses[array_search($entity->id, $this->insertIdMap)] ?? []
                    ])
                );
            }

            // - Emit entity save event
            $this->mm->getEventManager()->trigger(
                $this->getTargetTable()->getEntityClass()::getEventName('save', 'after'),
                $entity,
                array_merge($params, [ 'beforeResponses' => $eventSaveResponses[$entity->id] ?? [] ])
            );
        }

        # - Nullify __idinsert for the created entities
        if ($this->insertIdMap) {
            $tableGateway->update(['__idinsert' => null], ['__idinsert' => array_keys($this->insertIdMap)]);
        }

        # - Quit if processor of table type
        if ($this instanceof TableProcessor) {
            return;
        }

        # TODO: remove - Reinitialize reference keys mapping with really identifiers
        // foreach ($this->referenceKeysMap as &$identifiers) {
        //     foreach ($this->insertIdMap as $tempIdentifier => $id) {
        //         if (($index = array_search($tempIdentifier, $identifiers)) !== FALSE) {
        //             $identifiers[$index] = $id;
        //         }
        //     }
        // }
    }

    /**
     * saveReferences
     *
     * @param array $_insertIdMaps
     */
    public function saveReferences(array $_insertIdMaps) : void
    {
        if ($this instanceof TableProcessor || (! $this->referenceKeysMap && ! $this->unlinkedKeysMap)) {
            return;
        }

        foreach ($_insertIdMaps as $tempIndentifier => $id) {
            if (isset($this->referenceKeysMap[$tempIndentifier])) {
                $this->referenceKeysMap[$id] = $this->referenceKeysMap[$tempIndentifier];
                unset($this->referenceKeysMap[$tempIndentifier]);
            }
            if (isset($this->unlinkedKeysMap[$tempIndentifier])) {
                $this->unlinkedKeysMap[$id] = $this->unlinkedKeysMap[$tempIndentifier];
                unset($this->unlinkedKeysMap[$tempIndentifier]);
            }
        }

        $referenceMap = $this->reference->getReferenceMap();

        if (count($referenceMap) === 2) {

            # - Updates entities with references
            if ($referenceMap[0]->getName() == 'id') {
                $tableGateway = $this->gateway->getTableGateway($this->getTableName());
                $updateColumn = $referenceMap[1];
            } else {
                $tableGateway = $this->gateway->getTableGateway($referenceMap[0]->getTable()->getTableName());
                $updateColumn = $referenceMap[0];
            }

            # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! #
            # TODO: Control conditions of this reference #
            # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! #

            # - Insert/Update references
            if ($this->referenceKeysMap) {
                # - Prepare rowset entities
                $rowSet = [];
                foreach ($this->referenceKeysMap as $iReferenceEntity => $targetEntities) {
                    foreach ($targetEntities as $entity) {
                        if ($referenceMap[0]->getName() == 'id') {
                            $row = [
                                'id' => $entity->id,
                                $updateColumn->getName() => $iReferenceEntity,
                            ];
                        } else {
                            $row = [
                                'id' => $iReferenceEntity,
                                $updateColumn->getName() => $entity->id,
                            ];
                        }
                        $rowSet[] = $row;
                    }
                }

                # - Update references of entities
                $tableGateway->insert($rowSet, [$updateColumn->getName()]);
            }

            # - Delete references for unlinked entities
            if ($this->unlinkedKeysMap) {
                # - Prepare predicates for update
                $predicates = [];
                foreach ($this->unlinkedKeysMap as $iReferenceEntity => $targetEntities) {
                    $predicate = new Predicate\Predicate();
                    if ($referenceMap[0]->getName() == 'id') {
                        $predicate->addPredicates([
                            'id' => $targetEntities,
                            $updateColumn->getName() => $iReferenceEntity,
                        ], $predicate::OP_AND);
                    } else {
                        $predicate->addPredicates([
                            'id' => $iReferenceEntity,
                            $updateColumn->getName() => $targetEntities,
                        ], $predicate::OP_AND);
                    }
                    $predicates[] = $predicate;
                }

                $tableGateway->update(
                    [$updateColumn->getName() => null],
                    new Predicate\Predicate($predicates, Predicate\Predicate::OP_OR)
                );
            }

        } elseif (count($referenceMap) === 4) {

            # - Updates entities with references
            $tableGateway = $this->gateway->getTableGateway($referenceMap[1]->getTable()->getTableName());
            # - Conditions
            $conditions = $this->reference->getConditions()[$referenceMap[1]->getTable()->getEntityName()] ?? [];
            # - Columns
            $referenceColumns = $referenceMap[1]->getTable()->getColumns();

            if ($this->referenceKeysMap) {
                $rowSet = [];
                foreach ($this->referenceKeysMap as $iReferenceEntity => $targetEntities) {
                    foreach ($targetEntities as $entity) {
                        $row = [
                            $referenceMap[1]->getName() => $iReferenceEntity,
                            $referenceMap[2]->getName() => $entity->id,
                        ];
                        if ($conditions) {
                            foreach ($conditions as $condition) {
                                $row[$condition[0]->getName()] = $condition[1];
                            }
                        }

                        # - Check fields of entity for reference table
                        foreach ($referenceColumns as $column) {
                            $referenceColumnName = $this->getReferenceColumnName($column);
                            if (isset($entity[$referenceColumnName])) {
                                $row[$column->getName()] = $entity[$referenceColumnName];
                            }
                        }

                        $rowSet[] = $row;
                    }
                }
                # - Update references of entities
                $tableGateway->insert($rowSet, [$referenceMap[1]->getName(), $referenceMap[2]->getName()]);
            }

            # - Delete references for unlinked entities
            if ($this->unlinkedKeysMap) {
                # - Prepare predicates for update
                $predicates = [];
                foreach ($this->unlinkedKeysMap as $iReferenceEntity => $targetEntities) {
                    $predicate = new Predicate\Predicate();
                    foreach ($targetEntities as $iEntity) {
                        # - Predicates for entities references
                        $predicate->addPredicates([
                            $referenceMap[1]->getName() => $iReferenceEntity,
                            $referenceMap[2]->getName() => $iEntity,
                        ], $predicate::OP_AND);
                        # - Predicates for reference conditions if exists
                        if ($conditions) {
                            foreach ($conditions as $condition) {
                                $predicate->addPredicates([
                                    $condition[0]->getName() => $condition[1],
                                ], $predicate::OP_AND);
                            }
                        }
                    }
                    $predicates[] = $predicate;
                }

                $tableGateway->delete(
                    new Predicate\Predicate($predicates, Predicate\Predicate::OP_OR)
                );
            }
        }

        $this->gateway->saveReferences();
    }

    /**
     * deleteEntities
     *
     * @param Select $_select
     */
    public function deleteEntities(Sql\Select $_select = null) : bool
    {
        # - if table processor
        if ($_select === null) {

            $entities = $this->repository->getAll();

            # - Accomulate enitities id
            $toDelete = $responses = [];
            foreach ($entities as $entity) {
                # - Emit event delete before
                $responses[$entity->id] = $this->mm->getEventManager()->trigger(
                    $this->getTargetTable()->getEntityClass()::getEventName('delete', 'before'),
                    $entity,
                    [
                        'mm' => $this->mm,
                    ]
                );

                if ($responses[$entity->id]->contains(false)) {
                    continue;
                }

                $toDelete[] = $entity['id'];
            }

            # - Before delete references
            $this->gateway->deleteReferences(
                (new Sql\Select($this->getTableName()))->where(['id' => $toDelete])
            );

            # - After delete entities
            $tableGateway = $this->gateway->getTableGateway($this->getTableName());

            $tableGateway->delete(['id' => $toDelete]);

            # - Emit event delete before for each deleted entities
            foreach ($toDelete as $iEntity) {
                $this->mm->getEventManager()->trigger(
                    $this->getTargetTable()->getEntityClass()::getEventName('delete', 'after'),
                    $entities[$iEntity],
                    [
                        'beforeResponses' => $responses[$iEntity],
                        'mm' => $this->mm,
                    ]
                );
            }

        # - else reference processor
        } else {

            # - Clone select for this reference
            $_select = clone $_select;

            $referenceMap = $this->reference->getReferenceMap();

            # - If O2M or O2O reference
            if (count($referenceMap) == 2) {
                # - Conditions
                $conditions = $this->reference->getConditions()[$referenceMap[1]->getTable()->getEntityName()] ?? [];
                # - Table name of this entity
                $tableName = $this->getTableName();
                # - Filtering column name
                $filterColumn = $referenceMap[1];
                # - Object of TableGateway class
                $tableGateway = $this->gateway->getTableGateway($tableName);
                # - Object of Select class from base entity
                $_select->columns([$referenceMap[0]->getName()]);
                # - If strict mode then delete entities

                # - Prepare predicates
                $predicates = [new Predicate\In($filterColumn->getName(), $_select)];
                # - Prepare conditions if reference has it
                if ($conditions) {
                    # - Compare additional conditions for this reference
                    foreach ($conditions as $condition) {
                        $predicates[$condition[0]->getName()] = $condition[1];
                    }
                }

                if ($this->reference->getStrictMode()) {

                    $targetSelect = (new Sql\Select($tableName))->where($predicates);

                    # - Emit event before strict delete. Needs tests!
                    $this->mm->getEventManager()->trigger(
                        $this->getTargetTable()->getEntityClass()::getEventName('cascadeDelete', 'before'),
                        $this,
                        [ 'select' => $targetSelect, 'mm' => $this->mm ]
                    );

                    # - Execute delete entities and references which related with this entities
                    $this->gateway->deleteReferences($targetSelect);

                    # - After delete entities
                    $tableGateway->delete($predicates);

                    # - Emit event after strict delete
                    $this->mm->getEventManager()->trigger(
                        $this->getTargetTable()->getEntityClass()::getEventName('cascadeDelete', 'after'),
                        $this,
                        [ 'select' => $targetSelect, 'mm' => $this->mm ]
                    );

                # - Else update references
                } else {
                    # - Skip updates if is not direct reference
                    if ($referenceMap[0]->getName() !== 'id') {
                        return true;
                    }
                    # - Update reference target column
                    $tableGateway->update(
                        [$filterColumn->getName() => null],
                        $predicates
                    );
                }

            # - Else if M2M reference
            } elseif (count($referenceMap) === 4) {
                # - Get conditions for reference table
                $referenceConditions = $this->reference->getConditions()[$referenceMap[1]->getTable()->getEntityName()] ?? [];
                # - Get conditions for target entity table
                $targetConditions = $this->reference->getConditions()[$referenceMap[3]->getTable()->getEntityName()] ?? [];
                # - If strict mode then delete entities
                if ($this->reference->getStrictMode()) {
                    # - Object of Select class for reference table
                    $referenceSelect = new Sql\Select(
                        $referenceTableName = $referenceMap[1]->getTable()->getTableName()
                    );

                    # - Array of predicates for reference table select
                    $referencePredicates = [
                        new Predicate\In(
                            $referenceMap[1]->getName(),
                            $_select->columns([$referenceMap[0]->getName()])
                        )
                    ];

                    # - Compare additional conditions for reference table if exists
                    if ($referenceConditions) {
                        foreach ($referenceConditions as $condition) {
                            $referencePredicates[$condition[0]->getName()] = $condition[1];
                        }
                   }

                    # - Predicate set for reference table
                    $conditionsPredicates = [];

                    # - Compare additional conditions for target table if exists
                    if ($targetConditions) {
                        foreach ($targetConditions as $condition) {
                            $conditionsPredicates[$condition[0]->getName()] = $condition[1];
                        }
                    }

                    $referencePredicate = new Predicate\In(
                        $referenceMap[3]->getName(),
                        $referenceSelect
                            ->columns([$referenceMap[2]->getName()])
                            ->where($referencePredicates)
                    );

                    # - Object of Select class for target entity table
                    $targetSelect = (new Sql\Select($this->getTableName()))
                        ->where(array_merge($conditionsPredicates, [$referencePredicate]));
                    # - Delete references
                    $this->gateway->deleteReferences($targetSelect);

                    # - Delete entities
                    # - Emit event of cascade delete BEFORE
                    $this->mm->getEventManager()->trigger(
                        $this->getTargetTable()->getEntityClass()::getEventName('cascadeDelete', 'before'),
                        $this,
                        [ 'select' => $targetSelect, 'mm' => $this->mm ]
                    );

                    $tableGateway = $this->gateway->getTableGateway($this->getTableName());
                    $tableGateway->delete(array_merge($conditionsPredicates, [$referencePredicate]));

                    # - Emit event of cascade delete AFTER
                    $this->mm->getEventManager()->trigger(
                        $this->getTargetTable()->getEntityClass()::getEventName('cascadeDelete', 'after'),
                        $this,
                        [ 'select' => $targetSelect, 'mm' => $this->mm ]
                    );

                    # - Delete this references which referenced with targets entities for later removal
                    $tableGateway = $this->gateway->getTableGateway($referenceMap[2]->getTable()->getTableName());
                    $tableGateway->delete(
                        array_merge($conditionsPredicates, [
                            new Predicate\In(
                                $referenceMap[2]->getName(),
                                new Sql\Select([$referenceMap[2]->getTable()->getTableName() =>
                                    $referenceSelect
                                        ->columns([$referenceMap[2]->getName()])
                                        ->where($referencePredicates)
                                ])
                            )
                        ])
                    );
                }

                # - Table name which has reference rows
                $tableName = $referenceMap[1]->getTable()->getTableName();
                # - Filter by column which related with base entity
                $filterColumn = $referenceMap[1];
                # - Object of TableGateway class
                $tableGateway = $this->gateway->getTableGateway($tableName);
                # - Object of Select class from base entity
                $_select->columns([$referenceMap[0]->getName()]);
                # - Delete reference rows
                $tableGateway->delete(new Predicate\In($filterColumn->getName(), $_select));
            }
        }

        return true;
    }

    /**
     * getReferenceHash
     *
     */
    public function getReferenceHash() : ?string
    {
        if (static::class !== ReferenceProcessor::class) {
            return null;
        }

        return $this->reference->getReferenceHash();
    }

    /**
     * getInsertIdMap
     *
     */
    public function getInsertIdMap() : array
    {
        return $this->insertIdMap;
    }

    /**
     * flushRepository
     *
     */
    public function flushRepository() : void
    {
        $this->repository->flush();
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
     * setRepository
     *
     * @param Gateway\ProcessorRepository $_repository
     */
    public function setRepository(ProcessorRepositoryInterface $_repository)
    {
        $this->repository = $_repository;
    }

    /**
     * setEntityToRepository
     *
     * @param mixed $_entity
     */
    protected function setEntityToRepository($_entity) : Entity\EntityInterface
    {
        return $this->repository->set($_entity);
    }

    /**
     * getReferenceColumnName
     *
     */
    protected function getReferenceColumnName($column) : string
    {
        /* See Qant\ORM\Entity\Entity::__call(...)
         * create shared utility or trait for this functionality
        */
        return '@reference.' . $column->getAlias();
    }

}
