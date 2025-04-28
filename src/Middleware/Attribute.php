<?php declare(strict_types=1);

namespace Laravel\Coral\Middleware;

use Attribute as BaseAttribute;
use Closure;
use Laravel\Coral\Attribute\RequestAttribute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
#[BaseAttribute(BaseAttribute::TARGET_CLASS | BaseAttribute::TARGET_METHOD)]
class Attribute implements RequestAttribute
{
    /**
     * @param string $middlewareClass
     */
    public function __construct(public string $middlewareClass)
    {
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $middleware = new $this->middlewareClass();
        $next = static fn (Request $request) => $middleware->handle($request, $next);
        return $next($request);
    }
}
