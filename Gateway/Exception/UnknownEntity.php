<?php

namespace Qant\ORM\Gateway\Exception;

use Qant\ExceptionInterface;
use RuntimeException;

/**
 * @inheritDoc
 */
class UnknownEntity extends RuntimeException implements ExceptionInterface
{
}
