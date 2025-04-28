<?php declare(strict_types=1);

namespace Laravel\Coral\Controllers;

use Laravel\Coral\Provider;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\View\View;

use function app;
use function array_merge;
use function method_exists;

class Controller
{
    /*** @var array */
    protected array $assign = [];

    /*** @var string */
    protected string $moduleName;

    /**
     * RoutePrefix constructor.
     */
    public function __construct()
    {
        /*** @var Provider $moduleServiceProvider */
        try {
            $moduleServiceProvider = app()->make(Provider::class);
            if ($module = $moduleServiceProvider->controllerToModule[static::class] ?? null) {
                $this->setModuleName($module);
            }

            if (method_exists($this, 'initialize')) {
                app()->call([$this, 'initialize']);
            }
        } catch (BindingResolutionException $e) {

        }
    }

    /**
     * @return string
     */
    protected function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @param string $moduleName
     * @return void
     */
    protected function setModuleName(string $moduleName): void
    {
        $this->moduleName = $moduleName;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getViewName(string $name): string
    {
        return "{$this->moduleName}::{$name}";
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function assign(string $key, mixed $value): void
    {
        $this->assign[$key] = $value;
    }

    /**
     * @param string $template
     * @param array|null $data
     * @return View
     */
    protected function fetch(string $template, array $data = null): View
    {
        if ($data) {
            $this->assign = array_merge($this->assign, $data);
        }

        return \Illuminate\Support\Facades\View::make(
            "{$this->moduleName}::{$template}",
            $this->assign
        );
    }
}
