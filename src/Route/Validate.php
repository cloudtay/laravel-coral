<?php declare(strict_types=1);

namespace Laravel\Coral\Route;

use Attribute;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Coral\Attribute\RequestAttribute;
use Laravel\Coral\Exceptions\ValidateException;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD)]
class Validate implements RequestAttribute
{
    /**
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     * @param string $source
     * @param bool $autocomplete
     */
    public function __construct(
        public array  $rules = [],
        public array  $messages = [],
        public array  $attributes = [],
        public string $source = 'all',
        public bool $autocomplete = true
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

        if ($this->autocomplete && $validator->fails()) {
            throw new ValidateException($validator->errors()->first());
        }

        $request->route()->setParameter(\Illuminate\Validation\Validator::class, $validator);
        return $next($request);
    }
}
