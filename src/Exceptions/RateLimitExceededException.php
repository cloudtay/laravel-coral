<?php declare(strict_types=1);

namespace Laravel\Coral\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

use function array_merge;

class RateLimitExceededException extends TooManyRequestsHttpException
{
    /**
     * @param string $message
     * @param Throwable|null $previous
     * @param int $retryAfter
     * @param array $headers
     */
    public function __construct(
        string    $message = 'Too Many Requests',
        Throwable $previous = null,
        int       $retryAfter = 60,
        array     $headers = []
    ) {
        $headers = array_merge(['Retry-After' => $retryAfter], $headers);
        parent::__construct($retryAfter, $message, $previous, 429, $headers);
    }
}
