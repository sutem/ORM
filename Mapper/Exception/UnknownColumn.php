<?php

namespace Qant\ORM\Mapper\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownColumn extends RuntimeException implements ExceptionInterface
{
}
