<?php

namespace Qant\ORM\Mapper\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownReference extends RuntimeException implements ExceptionInterface
{
}
