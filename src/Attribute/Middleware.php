<?php declare(strict_types=1);

namespace Laravel\Coral\Attribute;

use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;
use function array_reverse;
use function is_subclass_of;
use function method_exists;

class Middleware
{
    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->buildPipeline($request, $next)($request);
    }

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Closure(Request): Response
     */
    protected function buildPipeline(Request $request, Closure $next): Closure
    {
        $middlewares = $this->resolveMiddlewares($request);
        $highPriority = [];
        $normalPriority = [];

        foreach ($middlewares as $middleware) {
            if ($middleware->highPriority ?? false) {
                $highPriority[] = $middleware;
            } else {
                $normalPriority[] = $middleware;
            }
        }

        $sortedMiddlewares = array_merge($highPriority, $normalPriority);
        foreach (array_reverse($sortedMiddlewares) as $middleware) {
            $next = static function (Request $request) use ($middleware, $next): Response {
                return $middleware->handle($request, $next);
            };
        }

        return $next;
    }

    /**
     * @param Request $request
     * @return RequestAttribute[]
     */
    protected function resolveMiddlewares(Request $request): array
    {
        $route = $request->route();
        if (!method_exists($route, 'getControllerClass') || !method_exists($route, 'getActionMethod')) {
            return [];
        }

        try {
            $class = new ReflectionClass($route->getControllerClass());
            $method = new ReflectionMethod($route->getControllerClass(), $route->getActionMethod());
        } catch (ReflectionException) {
            return [];
        }

        $middlewares = [];

        foreach ($class->getAttributes() as $attribute) {
            is_subclass_of($attribute->getName(), RequestAttribute::class) &&
            $middlewares[] = $attribute->newInstance();
        }

        foreach ($method->getAttributes() as $attribute) {
            is_subclass_of($attribute->getName(), RequestAttribute::class) &&
            $middlewares[] = $attribute->newInstance();
        }

        return $middlewares;
    }
}
