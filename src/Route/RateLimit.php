<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Closure;
use Laravel\Coral\Attribute\RequestAttribute;
use Laravel\Coral\Exceptions\RateLimitExceededException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

use function implode;

#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit implements RequestAttribute
{
    /**
     * @param int $maxAttempts 最大尝试次数
     * @param int $decayMinutes 重置时间（分钟）
     * @param string $key 限流键前缀
     * @param bool $byIp 是否按IP限流
     * @param bool $byUser 是否按用户限流
     */
    public function __construct(
        public int    $maxAttempts = 60,
        public int    $decayMinutes = 1,
        public string $key = 'api',
        public bool   $byIp = true,
        public bool   $byUser = false
    ) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws RateLimitExceededException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->buildRateLimitKey($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new RateLimitExceededException(
                "API rate limit exceeded. Try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($key, $this->decayMinutes * 60);
        $response = $next($request);
        $this->addRateLimitHeaders($response, $key);
        return $response;
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function buildRateLimitKey(Request $request): string
    {
        $parts = [$this->key];

        if ($this->byIp) {
            $parts[] = 'ip:' . $request->ip();
        }

        if ($this->byUser && $request->user()) {
            $parts[] = 'user:' . $request->user()->getAuthIdentifier();
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $parts[] = 'route:' . $routeName;
        } else {
            $parts[] = 'path:' . $request->path();
        }

        return implode('|', $parts);
    }

    /**
     * @param Response $response
     * @param string $key
     * @return void
     */
    protected function addRateLimitHeaders(Response $response, string $key): void
    {
        $remainingAttempts = RateLimiter::remaining($key, $this->maxAttempts);

        $response->headers->add([
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => $this->decayMinutes * 60
        ]);
    }
}
