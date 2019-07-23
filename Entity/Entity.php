<?php

declare(strict_types=1);

namespace Qant\ORM\Entity;

use \ArrayObject;
use Qant\ORM\ModelManager;
use Qant\ORM\Mapper\Mapper;
use Qant\ORM\Mapper\Table;
use Qant\EventManager\EventManager;

/**
 * Class: Entity
 *
 * @see EntityInterface
 * @see ArrayObject
 */
class Entity extends ArrayObject implements EntityInterface
{
    /**
     * entity
     *
     * @var mixed
     */
    protected $entityName = null;

    /**
     * unlinks
     *
     * @var mixed
     */
    protected $unlinks = [];

    /**
     * table
     *
     * @var mixed
     */
    protected $table = null;

    /**
     * isInitialize
     *
     * @var mixed
     */
    protected $isInitialize = false;

    /**
     * subscribes
     *
     * @var mixed
     */
    protected static $subscribes = [];

    /**
     * __construct
     *
     * @param string $_entity
     * @param mixed $_input
     * @param int $_flags
     * @param string $_iteratorClass
     */
    public function __construct(string $_entity, $_input = [], int $_flags=0, string $_iteratorClass='ArrayIterator')
    {
        $this->entityName = $_entity;

        parent::__construct([], $_flags, $_iteratorClass);

        $this->initialize($_input);
    }

    /**
     * initialize
     *
     * @param mixed $_input
     */
    private function initialize($_input)
    {
        $this->isInitialize = true;

        if (! isset($_input['id'])) {
            $_input['id'] = uniqid('new:', true);
        }

        if (! isset($_input['__version'])) {
            $_input['__version'] = 1;
        }

        foreach ($_input as $property => $value) {
            $this[$property] = $value;
        }

        $this->isInitialize = false;
    }

    /**
     * combine
     *
     * @param mixed $_input
     */
    public function combine($_input) : Entity
    {
        $this->isInitialize = true;

        foreach ($_input as $property => $value) {
            $this[$property] = $value;
        }

        $this->isInitialize = false;
        return $this;
    }

    /**
     * setTable
     *
     */
    public function setTable(Table\TableInterface $_table) : Entity
    {
        $this->table = $_table;
        return $this;
    }

    /**
     * __get
     *
     * @param string $_property
     */
    public function __get(string $_property)
    {
        return $this[$_property];
    }

    /**
     * __set
     *
     * @param string $_property
     * @param mixed $_value
     */
    public function __set(string $_property, $_value) : void
    {
        $this[$_property] = $_value;
    }

    /**
     * __invoke
     *
     * @param array $_entity
     * @param bool $_exchange
     */
    public function __invoke(array $_entity, bool $_exchange = false) : Entity
    {
        if ($_exchange) {
            $this->exchangeArray($_entity);
        } else {
            foreach ($_entity as $key => $value) {
                $this[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * __call
     *
     * @param mixed $_name
     * @param mixed $_arguments
     */
    public function __call($_name, $_arguments)
    {
        if (substr($_name, 0, 1) == '_') {
            $this['@reference.' . $_name] = $_arguments[0] ?? null;
        } else {
            throw new Exeception\EntityException(sprintf('Undefined method %s in %s class!', [
                $_name,
                static::class,
            ]));
        }
    }

    /**
     * offsetSet
     *
     * @param mixed $_key
     * @param mixed $_value
     */
    public function offsetSet($_key, $_value)
    {
        if ($_key !== '__version' && ! $this->isInitialize) {
            $this['__version']++;
        }

        parent::offsetSet($_key, $_value);
    }

    /**
     * offsetUnset
     *
     * @param mixed $_key
     */
    public function offsetUnset($_key)
    {
        $this['__version'] = microtime(true);
        parent::offsetUnset($_key);
    }

    /**
     * getEntityName
     *
     */
    public function getEntityName() : string
    {
        return $this->entityName;
    }

    /**
     * link
     *
     * @param mixed $_referenceName
     * @param mixed $_entities
     */
    public function link($_referenceName, $_entities) : Entity
    {
        return $this->setRelatedEntities($_referenceName, $_entities);
    }

    /**
     * unlink
     *
     * @param string $_reference
     * @param mixed $_entity
     */
    public function unlink(string $_reference, $_entity) : Entity
    {
        if (is_object($_entity) && $_entity instanceof Entity) {
            $this->unlinks[$_reference][] = $_entity['id'];
        }

        if (is_array($_entity) && isset($_entity['id'])) {
            $this->unlinks[$_reference][] = $_entity['id'];
        }

        if (is_scalar($_entity)) {
            $this->unlinks[$_reference][] = $_entity;
        }

        if (is_array($_entity) && !$this->isNumeric($_entity)) {
            foreach ($_entity as $entity) {
                $this->unlink($_reference, $entity);
            }
        }

        $this->unlinks[$_reference] = array_unique($this->unlinks[$_reference]);

        return $this;
    }

    /**
     * unlinkedEntities
     *
     * @param mixed $_referenceName
     */
    public function unlinkedEntities($_referenceName) : array
    {
        return $this->unlinks[$_referenceName] ?? [];
    }

    /**
     * setRelatedEntities
     *
     * @param mixed $_referenceName
     * @param mixed $_entities
     */
    public function setRelatedEntities($_referenceName, $_entities) : Entity
    {
        $this[$_referenceName] = $_entities;
        return $this;
    }

    /**
     * isNew
     *
     */
    public function isNew() : bool
    {
        return ! isset($this['id']) || substr($this['id'], 0, 3) === 'new';
    }

    /**
     * Simple test for a numeric array
     *
     * @param array $array
     */
    protected function isNumeric(array $array)
    {
        return preg_match('/^[0-9]+$/', implode('', array_keys($array))) ? true : false;
    }

    /**
     * initSubscribes
     *
     */
    public static function initSubscribes(EventManager $_em)
    {
        static::subscribe();
        self::subscribeSystem();

        if (! isset(static::$subscribes[static::class])) {
            return;
        }

        foreach (static::$subscribes[static::class] as $eventName => $closures) {
            foreach ($closures as $closure) {
                $_em->attach($eventName, $closure);
            }
        }
    }

    /**
     * subscribe
     *
     */
    public static function subscribe()
    {

    }

    /**
     * subscribeSystem
     *
     */
    final private static function subscribeSystem()
    {
        static::before('save', function($e){

            $entity = $e->getTarget();

            if (! isset($entity['tsCreated']) || ! $entity['tsCreated']) {
                $entity->tsCreated = new \DateTime();
            }

            if (! isset($entity['tsUpdated']) || ! $entity['tsUpdated']) {
                $entity->tsUpdated = new \DateTime();
            }

            if (is_object($entity->tsCreated) && $entity->tsCreated instanceof \DateTime) {
                $entity->tsCreated = $entity->tsCreated->format('Y-m-d H:i:s');
            }

            if (is_object($entity->tsUpdated) && $entity->tsUpdated instanceof \DateTime) {
                $entity->tsUpdated = $entity->tsUpdated->format('Y-m-d H:i:s');
            }
        });
    }

    /**
     * before
     *
     * @param mixed $_name
     */
    public static function before(string $_name, callable $_closure)
    {
        $currentSubscribes = static::$subscribes[static::class] ?? [];

        $eventName = static::getEventName($_name, 'before');
        $currentSubscribes[$eventName] = array_merge(
            $currentSubscribes[$eventName] ?? [],
            [$_closure]
        );

        static::$subscribes[static::class] = $currentSubscribes;
    }

    /**
     * after
     *
     * @param mixed $_name
     */
    public static function after(string $_name, callable $_closure)
    {
        $currentSubscribes = static::$subscribes[static::class] ?? [];

        $eventName = static::getEventName($_name, 'after');
        $currentSubscribes[$eventName] = array_merge(
            $currentSubscribes[$eventName] ?? [],
            [$_closure]
        );

        static::$subscribes[static::class] = $currentSubscribes;
    }

    /**
     * getEntityEventName
     *
     * @param mixed $_event
     * @param mixed $_factor
     */
    public static function getEventName($_event, $_factor) : string
    {
        return static::class . ':' . $_event . '.' . $_factor;
    }
}
