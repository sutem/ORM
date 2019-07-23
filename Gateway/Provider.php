<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

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
    public function get($_entity) : GatewayInterface
    {
        return new Gateway($_entity, $this->mm);
    }

}
