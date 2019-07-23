<?php

namespace Qant\ORM\Mapper\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownColumnType extends RuntimeException implements ExceptionInterface
{
}
