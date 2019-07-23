<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Qant\ORM\ModelManager;
use Qant\ORM\Entity;

class ProcessorRepository implements ProcessorRepositoryInterface
{
    /**
     * entity
     *
     * @var mixed
     */
    protected $entity = null;

    /**
     * _mm
     *
     * @var mixed
     */
    protected $_mm = null;

    /**
     * entities
     *
     * @var mixed
     */
    protected $entities = [];

    /**
     * __construct
     *
     * @param string $_entity
     * @param ModelManager $_mm
     */
    public function __construct(string $_entity, ModelManager $_mm)
    {
        $this->entity = $_entity;
        $this->mm = $_mm;
    }

    /**
     * set
     *
     * @param mixed $_entityData
     */
    public function set($_entityData) : Entity\EntityInterface
    {
        if (is_object($_entityData)) {
            if (! $_entityData instanceof Entity\EntityInterface) {
                throw new Exception\UnknownEntity(vsprintf('Entity object (%s) must be instance of %s', [get_class($_entity), Entity\EntityInterface::class]));
            }
            $entity = $_entityData;
            $this->entities[$_entityData->id] = $_entityData;
        } else {

            if (! isset($_entityData['__version'])) {
                $_entityData['__version'] = 0;
            }

            $entity = $this->mm->getEntity($this->entity, $_entityData);
            $this->entities[$entity->id] = $entity;
        }

        return $entity;
    }

    /**
     * unset
     *
     * @param mixed $_id
     */
    public function unset($_id)
    {
        unset($this->entities[$_id]);
    }

    /**
     * get
     *
     * @param mixed $_id
     */
    public function get($_id) : ?Entity\EntityInterface
    {
        return $this->entities[$_id] ?? null;
    }

    /**
     * getAll
     *
     */
    public function getAll() : array
    {
        return $this->entities;
    }

    /**
     * find
     *
     * @param mixed $_id
     */
    public function find($_id)
    {
        foreach ($this->storage as $key => $entity) {
            if ($entity->id === $_id) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * exchange
     *
     * @param array $_entiites
     */
    public function exchange(array $_entiites) : void
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
    public function getEntityName() : string
    {
        return $this->entity;
    }

}
