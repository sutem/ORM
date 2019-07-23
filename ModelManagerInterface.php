<?php

declare(strict_types=1);

namespace Qant\ORM;

use Qant\Database\Adapter\Adapter;

interface ModelManagerInterface
{
    public function getAdapter() : Adapter;
    public function getMapper(string $_entity) : Mapper\Mapper;
}
