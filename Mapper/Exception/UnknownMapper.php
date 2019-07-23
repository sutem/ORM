<?php

namespace Qant\ORM\Mapper\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownMapper extends RuntimeException implements ExceptionInterface
{
}
