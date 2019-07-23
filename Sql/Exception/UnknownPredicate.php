<?php

namespace Qant\ORM\Sql\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownPredicate extends RuntimeException implements ExceptionInterface
{
}
