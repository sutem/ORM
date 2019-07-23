<?php

namespace Qant\ORM\Mapper\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownTable extends RuntimeException implements ExceptionInterface
{
}
