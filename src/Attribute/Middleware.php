<?php declare(strict_types=1);

namespace Laravel\Coral\Attribute;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

use function app;

class Middleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if ($route instanceof Route) {
            $route->controller = app($route->getControllerClass());
        }
        $next = static fn (Request $request) => $next($request);
        $next = fn (Request $request) => $this->handleReflection($request, $next);

        return $next($request);
    }


    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws ReflectionException
     */
    public function handleReflection(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $controllerClass = $route->getControllerClass();
        $controllerMethod = $route->getActionMethod();
        $classReflectionClass = new ReflectionClass($controllerClass);
        $methodReflectionClass = new ReflectionMethod($controllerClass, $controllerMethod);

        $middlewares = [];

        foreach ($classReflectionClass->getAttributes() as $classAttribute) {
            $attributeObject = $classAttribute->newInstance();
            if ($attributeObject instanceof RequestAttribute) {
                $middlewares[$classAttribute->getName()] = $attributeObject;
            }
        }

        foreach ($methodReflectionClass->getAttributes() as $methodAttribute) {
            $attributeObject = $methodAttribute->newInstance();
            if ($attributeObject instanceof RequestAttribute) {
                $middlewares[$methodAttribute->getName()] = $attributeObject;
            }
        }

        foreach ($middlewares as $middleware) {
            $next = static fn (Request $request) => $middleware->handle($request, $next);
        }

        return $next($request);
    }
}
