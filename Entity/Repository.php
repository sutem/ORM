<?php

declare(strict_types=1);

namespace Qant\ORM\Entity;

use Qant\ORM\ModelManager;
use Qant\ORM\Entity;

/**
 * Class: Repository
 *
 * @see RepositoryInterface
 */
class Repository implements RepositoryInterface
{
    /**
     * entityClass
     *
     * @var mixed
     */
    protected $entityClass = null;

    /**
     * storage
     *
     * @var mixed
     */
    protected $storage = [];

    /**
     * __construct
     *
     * @param string $_entity
     */
    public function __construct(string $_entityClass)
    {
        $this->entityClass = $_entityClass;
    }

    /**
     * set
     *
     * @param EntityInterface $_entityData
     */
    public function set(EntityInterface $_entity) : EntityInterface
    {
        if (! $entity = $this->find($_entity)) {
            $this->storage[] = $entity = $_entity;
        } else {
            if ($entity['__version'] < $_entity['__version']) {
                $entity->combine($_entity);
            }
        }

        return $entity;
    }

    /**
     * unset
     *
     * @param mixed $_id
     */
    public function unset($_id) : bool
    {
        foreach ($this->storage as $key => $entity) {
            if ($entity->id === $_id) {
                unset($this->storage[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * find
     *
     * @param mixed $_id
     */
    public function find($_entityOrId) : ?EntityInterface
    {
        $entityId = $_entityOrId instanceof EntityInterface ? $_entityOrId['id'] : $_entityOrId;
        $entityInsertId = $_entityOrId instanceof EntityInterface && isset($_entityOrId['__idinsert'])? $_entityOrId['__idinsert'] : null;

        foreach ($this->storage as $key => $entity) {
            if ($entity->id === $entityId || (! is_null($entityInsertId) && $entity->__idinsert === $entityInsertId)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * getAll
     *
     */
    public function getAll() : array
    {
        return $this->storage;
    }

    /**
     * exchange
     *
     * @param array $_entities
     */
    public function exchange(array $_entities) : void
    {
        $this->entities = [];

        foreach ($_entities as $entity) {
            $this->set($entity);
        }
    }

    /**
     * count
     *
     */
    public function count() : int
    {
        return count($this->entities);
    }

    /**
     * flush
     *
     */
    public function flush() : void
    {
        $this->entities = [];
    }

    /**
     * getEntityName
     *
     */
    public function getEntityClass() : string
    {
        return $this->entityClass;
    }

}
