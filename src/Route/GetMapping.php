<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class GetMapping extends RouteMapping
{
    /**
     * @param string|null $route
     * @param string|null $name
     * @param array $wheres
     * @param array $middlewares
     */
    public function __construct(
        public string|null $route = '',
        public string|null $name = '',
        public array       $wheres = [],
        public array       $middlewares = []
    ) {
        parent::__construct('get', $route, $name, $wheres, $middlewares);
    }
}
