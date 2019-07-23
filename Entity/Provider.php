<?php

declare(strict_types=1);

namespace Qant\ORM\Entity;

use Qant\ORM;

class Provider implements ProviderInterface
{
    /**
     * mm
     *
     * @var mixed
     */
    protected $mm = null;

    /**
     * repositories
     *
     * @var mixed
     */
    private $repositories = [];

    /**
     * __construct
     *
     */
    public function __construct()
    {
    }

    /**
     * initialize
     *
     */
    public function initialize(ORM\ModelManager $_mm) : void
    {
        $this->mm = $_mm;
    }

    /**
     * get
     *
     */
    public function get(string $_entityName, array $_entityData = []) : EntityInterface
    {
        $mapper = $this->mm->getMapper($_entityName);
        $entityClass = $mapper->getEntityClass($_entityName);

        # - Emit event initialize before
        $responses = $this->mm->getEventManager()->trigger(
            $entityClass::getEventName('initialize', 'before'),
            null,
            [
                'entityName' => $_entityName,
                'entityData' => &$_entityData,
                'mm' => $this->mm,
            ]
        );

        $entity = $this->getRepository($_entityName)
            ->set($newEntity = new $entityClass($_entityName, $_entityData));

        # - Emit event initialize after
        $this->mm->getEventManager()->trigger(
            $entityClass::getEventName('initialize', 'after'),
            $entity,
            [
                'beforeResponses' => $responses,
                'mm' => $this->mm,
            ]
        );


        return $entity->setTable($mapper->getTable($_entityName));
    }

    /**
     * getRepository
     *
     */
    public function getRepository(string $_entityClass) : Repository
    {
        if (! isset($this->repositories[$_entityClass])) {
            $this->repositories[$_entityClass] = new Repository($_entityClass);
        }

        return $this->repositories[$_entityClass];
    }

}
