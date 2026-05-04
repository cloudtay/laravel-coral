<?php declare(strict_types=1);

namespace Laravel\Coral;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Application;
use Ripple\Worker\Manager;

use function app_path;
use function array_values;
use function class_exists;
use function config_path;
use function file_exists;
use function function_exists;
use function is_array;
use function is_dir;
use function is_string;
use function preg_match;
use function rtrim;
use function scandir;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

class Provider extends Module
{
    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $app->singleton(Manager::class, fn () => new Manager());
        $app->singleton(__CLASS__, fn () => $this);
        parent::__construct($app);
    }

    /**
     * @return void
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/coral.php', 'coral');

        foreach ($this->moduleLayers() as $layer) {
            foreach ($this->scanModules($layer['path']) as $module) {
                $moduleClass = "{$layer['namespace']}\\{$module}\\Module";

                if (class_exists($moduleClass)) {
                    $this->app->register($moduleClass);
                }
            }
        }
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../config/coral.php' => config_path('coral.php'),
            ], 'coral-config');
        }
    }

    /**
     * @param string|null $modulesPath
     * @return array
     */
    public function scanModules(?string $modulesPath = null): array
    {
        $modulesPath ??= app_path('Modules');
        if (!is_dir($modulesPath)) {
            return [];
        }

        $modules = scandir($modulesPath);
        if ($modules === false) {
            return [];
        }

        $moduleList = [];
        foreach ($modules as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $ignoreFile = $modulesPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . '.ignore';
            if (file_exists($ignoreFile)) {
                continue;
            }

            $moduleList[] = $module;
        }
        return $moduleList;
    }

    /**
     * @return array<int, array{path: string, namespace: string}>
     * @throws BindingResolutionException
     */
    protected function moduleLayers(): array
    {
        $configuredLayers = $this->app->make('config')->get('coral.module_layers', [app_path('Modules')]);
        if (!is_array($configuredLayers)) {
            $configuredLayers = [app_path('Modules')];
        }

        $layers = [];
        foreach ($configuredLayers as $layer) {
            $normalizedLayer = $this->normalizeModuleLayer($layer);
            if ($normalizedLayer !== null) {
                $layers[] = $normalizedLayer;
            }
        }

        return array_values($layers);
    }

    /**
     * @param mixed $path
     * @return array{path: string, namespace: string}|null
     */
    protected function normalizeModuleLayer(mixed $path): ?array
    {
        if (!is_string($path) || $path === '' || !$this->isAbsolutePath($path)) {
            return null;
        }

        $namespace = $this->namespaceFromAppPath($path);
        if ($namespace === null) {
            return null;
        }

        return [
            'path' => $path,
            'namespace' => $namespace,
        ];
    }

    protected function namespaceFromAppPath(string $path): ?string
    {
        $appPath = rtrim(str_replace('\\', '/', $this->app->path()), '/');
        $modulePath = rtrim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($modulePath, $appPath . '/')) {
            return null;
        }

        $relativePath = substr($modulePath, strlen($appPath) + 1);
        if ($relativePath === '') {
            return null;
        }

        return rtrim($this->app->getNamespace(), '\\') . '\\' . str_replace('/', '\\', $relativePath);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
