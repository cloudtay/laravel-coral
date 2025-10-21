<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Illuminate\Support\Facades\Route;
use Laravel\Coral\Attribute\BuildAttribute;
use ReflectionClass;
use ReflectionMethod;

use function is_array;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RouteMapping implements BuildAttribute
{
    /**
     * @param string|array $method
     * @param string|null $route
     * @param string|null $name
     * @param array $wheres
     * @param array $middlewares
     */
    public function __construct(
        public string|array $method = 'get',
        public string|null  $route = '',
        public string|null  $name = '',
        public array        $wheres = [],
        public array        $middlewares = []
    ) {
    }

    /**
     * @param mixed ...$params
     * @return true
     */
    public function handle(...$params): true
    {
        /**
         * @var ReflectionClass $classReflection
         * @var ReflectionMethod $methodReflection
         */
        $classReflection = $params[0];
        $methodReflection = $params[1];

        $methods = is_array($this->method)
            ? $this->method
            : [$this->method];

        $route = Route::match(
            $methods,
            $this->route,
            [$classReflection->getName(),$methodReflection->getName()]
        );

        $route->setWheres($this->wheres);
        $route->middleware($this->middlewares);
        return true;
    }
}
