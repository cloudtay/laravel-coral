<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Closure;
use Laravel\Coral\Attribute\RequestAttribute;
use Laravel\Coral\Exceptions\ValidateException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD)]
class Validate implements RequestAttribute
{
    /**
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     * @param string $source
     */
    public function __construct(
        public array  $rules = [],
        public array  $messages = [],
        public array  $attributes = [],
        public string $source = 'all'
    ) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws ValidateException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $data = match ($this->source) {
            'query' => $request->query(),
            'post' => $request->post(),
            default => $request->all(),
        };

        $validator = Validator::make(
            $data,
            $this->rules,
            $this->messages,
            $this->attributes
        );

        if ($validator->fails()) {
            throw new ValidateException($validator->errors()->first());
        }

        return $next($request);
    }
}
