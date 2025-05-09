<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Laravel\Coral\Attribute\ReadonlyAttribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RoutePrefix implements ReadonlyAttribute
{
    /**
     * @param string $prefix
     */
    public function __construct(
        public readonly string $prefix = ''
    ) {
    }
}
