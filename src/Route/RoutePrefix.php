<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RoutePrefix
{
}
