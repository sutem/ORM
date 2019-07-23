<?php

declare(strict_types=1);

namespace Qant\ORM;

use Qant\Database\Adapter\Adapter;
use Qant\EventManager;
use Qant\ORM\Mapper;
use Qant\ORM\Gateway;
use Qant\ORM\Entity;

class ModelManager implements ModelManagerInterface
{
    /**
     * adapter
     *
     * @var mixed
     */
    protected $adapter = null;

    /**
     * gatewayProvider
     *
     * @var mixed
     */
    protected $gatewayProvider = null;

    /**
     * entityProvider
     *
     * @var mixed
     */
    protected $entityProvider = null;

    /**
     * mapperProvider
     *
     * @var mixed
     */
    protected $mapperProvider = null;

    /**
     * eventManager
     *
     * @var mixed
     */
    protected $eventManager = null;

    /**
     * repositories
     *
     * @var mixed
     */
    protected $repositories = [];

    /**
     * __construct
     *
     * @param Adapter $_adapter
     * @param Gateway\ProviderInterface $_gatewayProvider
     * @param Entity\ProviderInterface $_entityProvider
     * @param Mapper\ProviderInterface $_mapperProvider
     * @param EventManager\EventManager $_eventManager
     */
    public function __construct(
        Adapter $_adapter,
        Gateway\ProviderInterface $_gatewayProvider,
        Entity\ProviderInterface $_entityProvider,
        Mapper\ProviderInterface $_mapperProvider,
        EventManager\EventManager $_eventManager
    )
    {
        $this->adapter = $_adapter;
        $this->eventManager = $_eventManager;

        $this->gatewayProvider = $_gatewayProvider;
        $this->gatewayProvider->initialize($this);

        $this->entityProvider = $_entityProvider;
        $this->entityProvider->initialize($this);

        $this->mapperProvider = $_mapperProvider;
        $this->mapperProvider->initialize($this);
    }

    /**
     * __invoke
     *
     * @param string $_model
     */
    public function __invoke($_entityName, array $_entity = null)
    {
        if (is_string($_entityName)) {
            return is_array($_entity) ? $this->getEntity($_entityName, $_entity) : $this->getGateway($_entityName);
        } elseif (is_object($_entityName)) {
            return $this->getGateway($_entityName);
        }
    }

    /**
     * getAdapter
     *
     */
    public function getAdapter() : Adapter
    {
        return $this->adapter;
    }

    /**
     * getGateway
     *
     * @param string $_entity
     */
    public function getGateway($_entity) : Gateway\GatewayInterface
    {
        return $this->gatewayProvider->get($_entity);
    }

    /**
     * getGateway
     *
     * @param string $_model
     */
    public function getEntity(string $_entityName, array $_entityData = []) : Entity\EntityInterface
    {
        return $this->entityProvider->get($_entityName, $_entityData);
    }

    /**
     * getMapperProvider
     *
     */
    public function getMapperProvider() : Mapper\ProviderInterface
    {
        return $this->mapperProvider;
    }

    /**
     * getMapper
     *
     * @param string $_entity
     */
    public function getMapper(string $_entity) : Mapper\Mapper
    {
        return $this->mapperProvider->get($_entity);
    }

    /**
     * getProcessorRepository
     *
     * @param string $_entity
     */
    public function getProcessorRepository(string $_entity) : Gateway\ProcessorRepository
    {
        return new Gateway\ProcessorRepository($_entity, $this);
    }

    /**
     * getGlobalRepository
     *
     * @param string $_entity
     */
    public function getGlobalRepository(string $_entity) : Gateway\Repository
    {

        if (! isset($this->globalRepositories[$_entity])) {
            $this->globalRepositories[$_entity] = new Gateway\Repository($_entity, $this);
        }

        return $this->globalRepositories[$_entity];
    }

    /**
     * getEventManager
     *
     */
    public function getEventManager() : EventManager\EventManager
    {
        return $this->eventManager;
    }

}
