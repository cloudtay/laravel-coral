<?php declare(strict_types=1);

namespace Laravel\Coral\Exceptions;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ValidateException extends BadRequestHttpException
{
}
