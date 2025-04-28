<?php declare(strict_types=1);

namespace Laravel\Coral\Database;

use Attribute;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Coral\Attribute\RequestAttribute;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Transaction implements RequestAttribute
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        return DB::transaction(static fn () => $next($request));
    }
}
