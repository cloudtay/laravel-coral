<?php declare(strict_types=1);

namespace Laravel\Coral\Attribute;

use Symfony\Component\HttpFoundation\Response;

interface BuildAttribute
{
    /**
     * @param mixed ...$params
     * @return Response
     */
    public function handle(mixed ...$params): mixed;
}
