<?php declare(strict_types=1);

namespace Laravel\Coral;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Coral\Controllers\Controller;
use Laravel\Coral\Route\RouteMapping;
use Laravel\Coral\Route\RoutePrefix;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Ripple\Worker\Manager;
use Throwable;

use function class_exists;
use function Co\forked;
use function get_class;
use function is_dir;
use function is_subclass_of;
use function pathinfo;
use function scandir;
use function str_starts_with;
use function strtolower;
use function file_exists;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_DIRNAME;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 *
 */
class Module extends ServiceProvider
{
    /*** @var string */
    public string $modulePath;

    /*** @var string */
    public string $moduleNamespace;

    /*** @var array */
    public array $controllerReflections = [];

    /*** @var string */
    public string $moduleName;

    /*** @var array */
    public array $controllerToModule = [];

    /*** @var array */
    public array $moduleControllers = [];

    /*** @param Application $app */
    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->build();
    }

    /**
     * @return void
     */
    protected function build(): void
    {
        $moduleClass = get_class($this);

        try {
            $reflection = new ReflectionClass($moduleClass);
        } catch (ReflectionException $e) {
            Log::error("Failed to create reflection for module class: {$moduleClass}", [
                'exception' => $e->getMessage()
            ]);
            return;
        }

        $this->modulePath = pathinfo($reflection->getFileName(), PATHINFO_DIRNAME);
        $this->moduleName = pathinfo($this->modulePath, PATHINFO_FILENAME);
        $this->moduleNamespace = $reflection->getNamespaceName();

        $this->registerViews();
        $this->registerComponents();
    }

    /**
     * @return void
     */
    protected function registerViews(): void
    {
        $viewPath = $this->modulePath . DIRECTORY_SEPARATOR . 'Views';
        if (is_dir($viewPath) && $this->app->has('view')) {
            View::addNamespace($this->moduleName, $viewPath);
        }
    }

    /**
     * @return void
     */
    protected function registerComponents(): void
    {
        $classes = $this->scanClassList($this->modulePath, $this->moduleNamespace);
        $pendingControllers = [];

        try {
            $manager = $this->app->make(Manager::class);
        } catch (BindingResolutionException $e) {
            Log::error("Failed to resolve Worker Manager", [
                'exception' => $e->getMessage()
            ]);

            return;
        }

        foreach ($classes as $class) {
            if (str_starts_with($class, "App\\Modules\\{$this->moduleName}\\Commands\\")) {
                $this->commands([$class]);
                continue;
            }

            if (str_starts_with($class, "App\\Modules\\{$this->moduleName}\\Workers\\")) {
                try {
                    $manager->add($this->app->make($class));
                } catch (BindingResolutionException $e) {
                    Log::warning("Failed to register worker: {$class}", [
                        'exception' => $e->getMessage()
                    ]);
                }
                continue;
            }
            $pendingControllers[] = $class;
        }

        forked(fn () => $this->registerControllers($pendingControllers));
    }

    /**
     * @param string $root
     * @param string $namespace
     * @return array
     */
    protected function scanClassList(string $root, string $namespace): array
    {
        $classList = [];

        if (!is_dir($root)) {
            return $classList;
        }

        $files = scandir($root);
        if ($files === false) {
            return $classList;
        }

        // Check for .ignore file to skip scanning
        $ignoreFile = $root . DIRECTORY_SEPARATOR . '.ignore';
        if (file_exists($ignoreFile)) {
            return $classList;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'Views') {
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $subClasses = $this->scanClassList($path, $namespace . '\\' . $file);
                foreach ($subClasses as $subClass) {
                    $classList[] = $subClass;
                }
            } else {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (strtolower($extension) === 'php') {
                    $className = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);
                    $classList[] = $className;
                }
            }
        }

        return $classList;
    }

    /**
     * @param array $controllers
     * @return void
     */
    protected function registerControllers(array $controllers): void
    {
        try {
            $kernelModule = $this->app->make(Provider::class);

            foreach ($controllers as $class) {
                try {
                    if (is_subclass_of($class, Controller::class)) {
                        $this->controllerReflections[$class] = new ReflectionClass($class);
                        $kernelModule->registerController($class, $this->moduleName);
                    }
                } catch (Throwable $e) {
                    Log::warning("Failed to register controller: {$class}", [
                        'exception' => $e->getMessage()
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error("Failed to register controllers", [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param string $controllerClass
     * @param string $module
     * @return void
     */
    protected function registerController(string $controllerClass, string $module): void
    {
        if (!class_exists($controllerClass)) {
            return;
        }
        $this->controllerToModule[$controllerClass] = $module;
        $classReflection = new ReflectionClass($controllerClass);
        if (isset($this->moduleControllers[$module])) {
            $this->moduleControllers[$module][] = $classReflection;
        } else {
            $this->moduleControllers[$module] = [$classReflection];
        }
        $this->controllerReflections[$controllerClass] = $classReflection;
        $this->registerRoutes($classReflection);
    }

    /**
     * @param ReflectionClass $classReflection
     * @return void
     */
    protected function registerRoutes(ReflectionClass $classReflection): void
    {
        $routeAttributes = [];

        foreach ($classReflection->getAttributes() as $controllerAttribute) {
            $controllerAttributeObject = $controllerAttribute->newInstance();
            if ($controllerAttributeObject instanceof RoutePrefix) {
                $routeAttributes['prefix'] = $controllerAttributeObject->prefix;
            }
        }

        Route::group($routeAttributes, static function () use ($classReflection) {
            foreach ($classReflection->getMethods() as $method) {
                if ($method->getModifiers() & ReflectionMethod::IS_PUBLIC) {
                    foreach ($method->getAttributes() as $methodAttribute) {
                        $methodAttributeObject = $methodAttribute->newInstance();
                        if ($methodAttributeObject instanceof RouteMapping) {
                            $methodAttributeObject->handle($classReflection, $method);
                        }
                    }
                }
            }
        });
    }
}
