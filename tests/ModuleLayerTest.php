<?php declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Laravel\Coral\Provider;

require __DIR__ . '/../vendor/autoload.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        \fwrite(\STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$basePath = \sys_get_temp_dir() . '/laravel-coral-module-layers-' . \uniqid('', true);
\mkdir($basePath . '/app/Modules/Blog', 0777, true);
\mkdir($basePath . '/app/Plugins/Shop', 0777, true);
\mkdir($basePath . '/app/Plugins/Ignored', 0777, true);

\file_put_contents($basePath . '/composer.json', \json_encode([
    'autoload' => [
        'psr-4' => [
            'App\\' => 'app/',
        ],
    ],
], \JSON_THROW_ON_ERROR));

\file_put_contents($basePath . '/app/Modules/Blog/Module.php', <<<'PHP'
<?php declare(strict_types=1);

namespace App\Modules\Blog;

class Module extends \Laravel\Coral\Module
{
    public function register(): void
    {
    }
}
PHP);

\file_put_contents($basePath . '/app/Plugins/Shop/Module.php', <<<'PHP'
<?php declare(strict_types=1);

namespace App\Plugins\Shop;

class Module extends \Laravel\Coral\Module
{
    public function register(): void
    {
    }
}
PHP);

\file_put_contents($basePath . '/app/Plugins/Ignored/Module.php', <<<'PHP'
<?php declare(strict_types=1);

namespace App\Plugins\Ignored;

class Module extends \Laravel\Coral\Module
{
    public function register(): void
    {
    }
}
PHP);
\touch($basePath . '/app/Plugins/Ignored/.ignore');

\spl_autoload_register(static function (string $class) use ($basePath): void {
    if (!\str_starts_with($class, 'App\\')) {
        return;
    }

    $path = $basePath . '/app/' . \str_replace('\\', '/', \substr($class, 4)) . '.php';
    if (\is_file($path)) {
        require $path;
    }
});

$app = new Application($basePath);
$app->instance('config', new Repository([
    'coral' => [
        'module_layers' => [
            $app->path('Modules'),
            $app->path('Plugins'),
        ],
    ],
]));

$provider = new Provider($app);
\assertTrue($provider->scanModules() === ['Blog'], 'keeps default app/Modules scan behavior');

$provider->register();

\assertTrue($app->getProvider(App\Modules\Blog\Module::class) !== null, 'registers modules from app/Modules');
\assertTrue($app->getProvider(App\Plugins\Shop\Module::class) !== null, 'registers modules from configured app/Plugins layer');
\assertTrue($app->getProvider(App\Plugins\Ignored\Module::class) === null, 'keeps .ignore behavior in configured module layers');

echo "ModuleLayerTest passed\n";
