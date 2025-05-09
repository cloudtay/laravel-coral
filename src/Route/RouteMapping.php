<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Illuminate\Support\Facades\Route;
use Laravel\Coral\Attribute\BuildAttribute;
use Laravel\Coral\Attribute\Middleware;

use function is_array;
use function array_merge;

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
        $action = $params[0];

        $methods = is_array($this->method)
            ? $this->method
            : [$this->method];

        $route = Route::match(
            $methods,
            $this->route,
            $action
        );

        $route->setWheres($this->wheres);
        $route->middleware(array_merge($this->middlewares, [
            Middleware::class
        ]));
        return true;
    }
}
