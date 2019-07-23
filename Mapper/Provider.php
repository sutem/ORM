<?php

declare(strict_types=1);

namespace Qant\ORM\Mapper;

use Qant\ORM;

class Provider implements ProviderInterface
{
    /**
     * mm
     *
     * @var ORM\ModelManager
     */
    protected $mm = null;

    /**
     * mappers
     *
     * @var Mapper[]
     */
    protected $mappers = [];

    /**
     * __construct
     *
     */
    public function __construct()
    {
    }

    /**
     * registerMapper
     *
     * @param Mapper $_mapper
     */
    public function registerMapper(Mapper $_mapper) : void
    {
        $_mapper->setProvider($this);
        if (! $_mapper->hasModelManager() && ! is_null($this->mm)) {
            $_mapper->setModelManager($this->mm);
        }

        $this->mappers[] = $_mapper;
    }

    /**
     * initialize
     *
     */
    public function initialize(ORM\ModelManager $_mm) : void
    {
        $this->mm = $_mm;

        foreach ($this->mappers as $mapper) {
            $mapper->setModelManager($this->mm);
            $mapper->initReferences();
            $mapper->initSubscribes();
        }
    }

    /**
     * get
     *
     */
    public function get(string $_entity) : Mapper
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->has($_entity)) {
                return $mapper;
            }
        }

        throw new Exception\UnknownMapper(vsprintf('Undefined mapper for entity (%s)', [$_entity]));
    }
}
