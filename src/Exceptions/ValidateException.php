<?php declare(strict_types=1);

namespace Laravel\Coral\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ValidateException extends TooManyRequestsHttpException
{
}
