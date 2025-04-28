<?php declare(strict_types=1);

namespace Laravel\Coral\Middleware;

use Attribute as BaseAttribute;
use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Coral\Attribute\RequestAttribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function app;
use function method_exists;

/**
 *
 */
#[BaseAttribute(BaseAttribute::TARGET_CLASS | BaseAttribute::TARGET_METHOD)]
class Attribute implements RequestAttribute
{
    /**
     * @param string $middlewareClass
     * @param bool $highPriority
     */
    public function __construct(
        public string $middlewareClass,
        public bool   $highPriority = false
    ) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $middleware = app()->make($this->middlewareClass);
        } catch (BindingResolutionException $e) {
            Log::error('Middleware not found: ' . $this->middlewareClass, [
                'exception' => $e,
            ]);

            throw new TooManyRequestsHttpException(null, "Middleware not found: {$this->middlewareClass}");
        }

        if (!method_exists($middleware, 'handle')) {
            throw new TooManyRequestsHttpException(null, "Middleware not found: {$this->middlewareClass}");
        }

        $next = static fn (Request $request) => $middleware->handle($request, $next);
        return $next($request);
    }
}
