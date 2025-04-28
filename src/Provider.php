<?php declare(strict_types=1);

namespace Laravel\Coral;

use Illuminate\Foundation\Application;
use Ripple\Worker\Manager;

use function app_path;
use function class_exists;
use function is_dir;
use function scandir;

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
     */
    public function register(): void
    {
        $modulesPath = app_path('Modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach ($this->scanModules() as $module) {
            $moduleClass = "App\\Modules\\{$module}\\Module";

            if (class_exists($moduleClass)) {
                $this->app->register($moduleClass);
            }
        }
    }

    /**
     * @return array
     */
    public function scanModules(): array
    {
        $modulesPath = app_path('Modules');
        if (!is_dir($modulesPath)) {
            return [];
        }

        $modules = scandir($modulesPath);
        $moduleList = [];
        foreach ($modules as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }
            $moduleList[] = $module;
        }
        return $moduleList;
    }
}
