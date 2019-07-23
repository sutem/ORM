<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Qant\ORM;
use Qant\ORM\Entity;
use Qant\ORM\Mapper;
use Qant\ORM\Sql;
use Qant\Collection\Collection;
use Zend\Db\Sql as ZendSql;
use Zend\Db\ResultSet\ResultSet;

class Gateway implements GatewayInterface
{
    /**
     * entity
     *
     * @var string
     */
    protected $entity = null;

    /**
     * mm
     *
     * @var mixed
     */
    protected $mm = null;

    /**
     * processor
     *
     * @var mixed
     */
    protected $processor = null;

    /**
     * processors
     *
     * @var mixed
     */
    protected $processors = [];

    /**
     * processorPathPreffix
     *
     * @var mixed
     */
    protected $processorPathPreffix = null;

    /**
     * select
     *
     * @var mixed
     */
    protected $select = null;

    /**
     * where
     *
     * @var mixed
     */
    protected $where = null;

    /**
     * repository
     *
     * @var mixed
     */
    protected $repository = null;

    /**
     * __construct
     *
     * @param mixed $_entity
     * @param ORM\ModelManager $_mm
     */
    public function __construct($_entity, ORM\ModelManager $_mm)
    {
        $this->mm = $_mm;

        # - If entity is instance of EntityInterface
        if (is_object($_entity)) {
            if (! $_entity instanceof Entity\EntityInterface) {
                throw new Exception\UnknownEntity(vsprintf('Entity object (%s) must be instance of %s', [get_class($_entity), Entity\EntityInterface::class]));
            }
            $this->entity = $_entity->getEntityName();
            $repository = $this->mm->getProcessorRepository($this->entity);
            $repository->set($_entity);

        # - If entity is string of entity name
        } else {
            $this->entity = $_entity;
            $repository = $this->mm->getProcessorRepository($this->entity);
        }

        $this->mapper = $this->mm->getMapper($this->entity);
        $this->adapter = $this->mm->getAdapter();

        $this->processor = new TableProcessor(
            $this->mapper->getTable($this->entity),
            $repository,
            $this,
            $this->mm
        );

        $this->initialize();
    }

    /**
     * initilize
     *
     */
    public function initialize(): void
    {
        # - Init where object
        $this->where = new Sql\Where();
        $this->where->setGateway($this);

        # - Init select object
        $this->select = new Sql\Select();
        $this->select->where($this->where);

        $this->processorPathPreffix = $this->processor->getEntityName();
        $this->processors[$this->processorPathPreffix] = $this->processor;

        # - If repository initilized with entities
        if ($this->processor->getRepository()->count()) {
            $this->initReferencesFromEntities($this->processor->getRepository()->getAll());
        }
    }

    /**
     * getTableGateway
     *
     * @param string $_tableName
     */
    public function getTableGateway(string $_tableName = null) : TableGateway
    {
        if (is_null($_tableName)) {
            $_tableName = $this->processor->getTableName();
        }

        return new TableGateway(
            $_tableName,
            $this->mm->getAdapter(),
            null,
            new ResultSet(ResultSet::TYPE_ARRAY),
            new Sql\Sql(
                $this->mm->getAdapter(),
                $_tableName
            )
        );
    }

    /**
     * with
     *
     * @param string $_reference
     * @param \Closure $_callback
     */
    public function with(string $_reference, \Closure $_callback=null) : GatewayInterface
    {
        $processorPath = $this->prepareProcessorPath($_reference);

        $referenceProcessor = new ReferenceProcessor(
            $this->processor->getReference($_reference),
            null,
            $this->processor,
            $this,
            $this->mm
        );

        $this->processors[$processorPath] = $referenceProcessor;

        if ($_callback) {

            # - Save current last reference and set new
            $currentProcessor = $this->processor;
            $this->processor = $referenceProcessor;

            # - Save current reference path preffix and set new
            $currentProcessorPathPreffix = $this->processorPathPreffix;
            $this->processorPathPreffix = $processorPath;

            # - Evaluate reference closure
            $_callback($this);

            # - Restore current subjects
            $this->processor = $currentProcessor;
            $this->processorPathPreffix = $currentProcessorPathPreffix;
        }

        return $this;
    }

    /**
     * select
     *
     * @param Sql\Select $_select
     */
    public function select(ZendSqlSelect $_select = null)
    {
        if ($_select) {
            $this->select = $_select;
            $this->select->where(
                $this->where->setPredicates($_select->where->getPredicates())
            );
            return $this;
        }

        return $this->select;
    }

    /**
     * where
     *
     * @param mixed $_where
     */
    public function where(\Closure $_where) : Gateway
    {
        # - Process where closure
        $_where($this->where);

        # - Prepare predicates
        $this->where->prepareCurrentTablePredicates();

        return $this;
    }

    /**
     * all
     *
     */
    public function all()
    {
        # - Prepare sql with all references
        $this->prepareSelect();

        # - Execute sql statement and parse results
        $this->parseResult(
            $this->getTableGateway($this->processor->getTableName())
                 ->selectWith($this->select())
        );

        # - Save return array of entities
        $return = $this->compareEntities($this->processor->getRepository()->getAll());

        # - Flush repositories
        $this->flushRepositories();

        return $return;
    }

    /**
     * one
     *
     */
    public function one()
    {
        return $this->all()->first();
    }

    /**
     * save
     *
     */
    public function save()
    {
        foreach ($this->processors as $processor) {
            $processor->saveEntities();
        }

        $this->saveReferences();

        return $this->compareEntities($this->processor->getRepository()->getAll());
    }

    /**
     * saveReferences
     *
     */
    public function saveReferences()
    {
        foreach ($this->processors as $processorPath => $processor) {

            # - If namespace of processor relevant for current namespace
            if (! $this->matchProcessorPath($processorPath)) {
                continue;
            }

            # - Save current processor
            $currentProcessor = $this->processor;
            $this->processor = $processor;

            # - Save current path preffix
            $currentProcessorPathPreffix = $this->processorPathPreffix;
            $this->processorPathPreffix = $processorPath;

            # - Run entity compare
            $processor->saveReferences($currentProcessor->getInsertIdMap());

            # - Restore processor
            $this->processor = $currentProcessor;
            # - Restore reference path preffix
            $this->processorPathPreffix = $currentProcessorPathPreffix;
        }
    }

    /**
     * delete
     *
     */
    public function delete()
    {
        return $this->deleteReferences();
    }

    /**
     * deleteReferences
     *
     * @param Select $_select
     */
    public function deleteReferences(ZendSql\Select $_select = null) : void
    {
        if ($_select === null) {
            $this->processor->deleteEntities();
            return;
        }

        $references = $this->processor->getTableReferences();
        foreach ($references as $referenceName => $reference) {
            # - Continue if this is parent reference
            if ($this->processor->getReferenceHash() === $reference->getReferenceHash()) {
                continue;
            }

            # - prepare processor path
            $processorPath = $this->prepareProcessorPath($referenceName);
            # - create reference processor
            $referenceProcessor = new ReferenceProcessor(
                $reference,
                null,
                $this->processor,
                $this,
                $this->mm
            );
            # - save reference processor
            $this->processors[$processorPath] = $referenceProcessor;
            # - Save current last reference and set new
            $currentProcessor = $this->processor;
            $this->processor = $referenceProcessor;
            # - Save current reference path preffix and set new
            $currentProcessorPathPreffix = $this->processorPathPreffix;
            $this->processorPathPreffix = $processorPath;
            # - delete references
            $this->processor->deleteEntities($_select);
            # - Restore current subjects
            $this->processor = $currentProcessor;
            $this->processorPathPreffix = $currentProcessorPathPreffix;
        }
    }

    /**
     * getRepository
     *
     */
    public function getRepository()
    {
        return $this->processor->getRepository();
    }

    /**
     * getReferenceContract
     *
     */
    public function getProcessor() : ReferenceProcessorInterface
    {
        return $this->processor;
    }

    /**
     * getReferencePathPreffix
     *
     */
    public function getProcessorPathPreffix() : string
    {
        return $this->processorPathPreffix;
    }

    /**
     * initReferencesFromEntities
     *
     */
    public function initReferencesFromEntities(array $_entities) : void
    {
        $references = $this->processor->getTableReferences();
        foreach ($references as $referenceName => $reference) {
            $referenceEntities = $unlinkedEntities = [];
            foreach ($_entities as $entity) {
                if (isset($entity[$referenceName])) {
                    if (is_object($entity[$referenceName]) && $entity[$referenceName] instanceof Entity\Entity) {
                        $referenceEntities[$entity['id']] = [$entity[$referenceName]];
                    } elseif (is_object($entity[$referenceName]) && $entity[$referenceName] instanceof Collection) {
                        $referenceEntities[$entity['id']] = $entity[$referenceName]->toArray();
                    } else {
                        $referenceEntities[$entity['id']] = $entity[$referenceName];
                    }
                }

                if ($unlinked = $entity->unlinkedEntities($referenceName)) {
                    $unlinkedEntities[$entity['id']] = $unlinked;
                }
            }

            if (! $referenceEntities && ! $unlinkedEntities) {
                continue;
            }

            $processorPath = $this->prepareProcessorPath($referenceName);

            $referenceProcessor = new ReferenceProcessor(
                $reference,
                null,
                $this->processor,
                $this,
                $this->mm
            );

            $this->processors[$processorPath] = $referenceProcessor;

            # - Save current last reference and set new
            $currentProcessor = $this->processor;
            $this->processor = $referenceProcessor;

            # - Save current reference path preffix and set new
            $currentProcessorPathPreffix = $this->processorPathPreffix;
            $this->processorPathPreffix = $processorPath;

            if ($referenceEntities) {
                # - Save entities to reference entities
                $this->processor->setEntitiesToRepository($referenceEntities);
                # - Recursive init reference entities
                $allReferenceEntities = [];
                foreach ($referenceEntities as $entities) {
                    $allReferenceEntities = array_merge($allReferenceEntities, $entities);
                }
                $this->initReferencesFromEntities($allReferenceEntities);
            }

            if ($unlinkedEntities) {
                # - Set unlinked entities
                $this->processor->unlinkEntities($unlinkedEntities);
            }

            # - Restore current subjects
            $this->processor = $currentProcessor;
            $this->processorPathPreffix = $currentProcessorPathPreffix;
        }
    }

    /**
     * compareEntities
     *
     */
    public function compareEntities(array $_entities = []) : Collection
    {
        $processors = array_slice($this->processors, 1);
        foreach ($_entities as $entity) {
            foreach ($processors as $processorPath => $reference) {
                # - check if that reference relate for this referencePathPreffix
                if (! $this->matchProcessorPath($processorPath)) {
                    continue;
                }
                # - Save current path preffix
                $currentProcessorPathPreffix = $this->processorPathPreffix;
                $this->processorPathPreffix = $processorPath;
                # - Run entity compare
                $reference->compareEntity($entity);
                # - Restore reference path preffix
                $this->processorPathPreffix = $currentProcessorPathPreffix;
            }
        }

        return new Collection($_entities);
    }

    /**
     * parseResult
     *
     * @param mixed $_result
     */
    protected function parseResult($_result) : void
    {
        foreach ($_result as $row) {
            foreach ($this->processors as $processor) {
                $processor->parseResult($row);
            }
        }
    }

    /**
     * prepareSql
     *
     */
    public function prepareSelect()
    {
        # - Prepare references
        $processorPathPreffixes = [];
        foreach ($this->processors as $processorPath => $referenceProcessor) {
            $processorPathPreffixes[$processorPath] = $referenceProcessor;
        }

        foreach ($this->processors as $processorPath => $referenceProcessor) {

            # - Save current last reference
            $currentProcessor = $this->processor;
            $this->processor = $processorPathPreffixes[
                substr($processorPath, 0, strripos($processorPath, '.') ?: strlen($processorPath))
            ];

            $referenceProcessor->prepareSelect();

            # - Restore current last reference
            $this->processor = $currentProcessor;
        }

        # - Prepare where sql
        $processorPathPreffixes = [];
        foreach ($this->processors as $processorPath => $referenceProcessor) {
            $processorPathPreffixes[$processorPath] = $referenceProcessor->getTableAlias();
        }

        krsort($processorPathPreffixes);
        $this->where->replaceReferencePathsToAliases($processorPathPreffixes);

        return $this->select;
    }

    /**
     * prepareReferenceContact
     *
     */
    protected function prepareProcessorPath(string $_reference) : string
    {
        return $this->processorPathPreffix . '.' . $_reference;
    }

    /**
     * matchReferencePath
     *
     * @param string $_referencePath
     */
    protected function matchProcessorPath(string $_processorPath) : bool
    {
        return $this->processorPathPreffix === substr($_processorPath, 0, strripos($_processorPath, '.') ?: strlen($_processorPath));
    }

    /**
     * flushRepositories
     *
     */
    protected function flushRepositories()
    {
        foreach ($this->processors as $reference) {
            $reference->flushRepository();
        }
    }

}
