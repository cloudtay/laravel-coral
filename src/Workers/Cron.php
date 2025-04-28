<?php declare(strict_types=1);

namespace Laravel\Coral\Workers;

use Closure;
use Ripple\Utils\Output;
use Ripple\Worker;
use Ripple\Worker\Manager;
use Throwable;

use function array_keys;
use function array_merge;
use function array_slice;
use function array_unshift;
use function call_user_func;
use function Co\go;
use function count;
use function date;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function explode;
use function is_numeric;
use function microtime;
use function sprintf;
use function strtotime;
use function time;
use function trim;
use function uniqid;

class Cron extends Worker
{
    /*** @var array 定时任务列表 */
    protected static array $tasks = [];

    /*** @var array 任务执行历史 */
    protected static array $history = [];

    /*** @var int 历史记录保留数量 */
    protected static int $historyLimit = 100;

    /*** @var string 默认时区 */
    protected static string $timezone = 'UTC';

    /**
     * @param Closure $closure 要执行的闭包函数
     * @param int|float|string $expression 时间间隔(秒)或Cron表达式
     * @param string $name 任务名称
     * @param array $options 额外选项
     * @return string 任务ID
     */
    public static function add(
        Closure          $closure,
        int|float|string $expression = 60,
        string           $name = 'cron',
        array            $options = []
    ): string {
        $id = uniqid('cron_', true);
        self::$tasks[$id] = [
            'callback' => $closure,
            'expression' => $expression,
            'name' => $name,
            'options' => array_merge([
                'timezone' => self::$timezone,
                'enabled' => true,
                'max_runtime' => 0,
                'max_attempts' => 0,
                'attempts' => 0,
                'last_run' => null,
                'next_run' => null,
            ], $options)
        ];

        self::calculateNextRunTime($id);
        return $id;
    }

    /**
     * 计算下次运行时间
     *
     * @param string $id 任务ID
     * @return void
     */
    protected static function calculateNextRunTime(string $id): void
    {
        if (!isset(self::$tasks[$id])) {
            return;
        }

        $task = &self::$tasks[$id];
        $expression = $task['expression'];
        $timezone = $task['options']['timezone'];

        // 设置当前时区
        $currentTz = date_default_timezone_get();
        date_default_timezone_set($timezone);

        if (is_numeric($expression)) {
            $nextRun = time() + (int)$expression;
        } else {
            $nextRun = self::getNextRunTimeFromCronExpression($expression);
        }

        $task['options']['next_run'] = $nextRun;

        // 恢复原来的时区
        date_default_timezone_set($currentTz);
    }

    /**
     * 从Cron表达式计算下次运行时间
     *
     * @param string $expression Cron表达式
     * @return int 下次运行时间戳
     */
    protected static function getNextRunTimeFromCronExpression(string $expression): int
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            return match ($expression) {
                '@yearly', '@annually' => strtotime('next year'),
                '@monthly' => strtotime('first day of next month midnight'),
                '@weekly' => strtotime('next monday'),
                '@daily', '@midnight' => strtotime('tomorrow midnight'),
                '@hourly' => strtotime('next hour'),
                default => strtotime('next minute'),
            };
        }

        // 简化版本 - 只获取下一分钟的时间
        return strtotime('+1 minute');
    }

    /**
     * @param string $id 任务ID
     * @return bool 是否成功移除
     */
    public static function remove(string $id): bool
    {
        if (isset(self::$tasks[$id])) {
            unset(self::$tasks[$id]);
            return true;
        }
        return false;
    }

    /**
     * @param string $id 任务ID
     * @return bool 是否成功暂停
     */
    public static function disable(string $id): bool
    {
        if (isset(self::$tasks[$id])) {
            self::$tasks[$id]['options']['enabled'] = false;
            return true;
        }
        return false;
    }

    /**
     * @param string $id 任务ID
     * @return bool 是否成功启用
     */
    public static function enable(string $id): bool
    {
        if (isset(self::$tasks[$id])) {
            self::$tasks[$id]['options']['enabled'] = true;
            self::calculateNextRunTime($id);
            return true;
        }
        return false;
    }

    /**
     * @return array 任务列表
     */
    public static function getTasks(): array
    {
        return self::$tasks;
    }

    /**
     * @param int $limit 限制数量
     * @return array 执行历史
     */
    public static function getHistory(int $limit = 20): array
    {
        return array_slice(self::$history, 0, $limit);
    }

    /**
     * @param string $timezone 时区
     * @return void
     */
    public static function setTimezone(string $timezone): void
    {
        self::$timezone = $timezone;
    }

    /**
     * @param int $limit 限制数量
     * @return void
     */
    public static function setHistoryLimit(int $limit): void
    {
        self::$historyLimit = $limit;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        go(static function () {
            while (1) {
                self::runDueTasks();
                \Co\sleep(1);
            }
        });
    }

    /**
     *
     * @return void
     */
    protected static function runDueTasks(): void
    {
        $now = time();
        foreach (self::$tasks as $id => &$task) {

            if (!$task['options']['enabled']) {
                continue;
            }

            if ($task['options']['max_attempts'] > 0 &&
                $task['options']['attempts'] >= $task['options']['max_attempts']) {
                continue;
            }

            if ($task['options']['next_run'] === null || $now < $task['options']['next_run']) {
                continue;
            }

            self::executeTask($id, $task);
            self::calculateNextRunTime($id);
        }

        if (count(self::$history) > self::$historyLimit) {
            self::$history = array_slice(self::$history, 0, self::$historyLimit);
        }
    }

    /**
     * 执行任务
     *
     * @param string $id 任务ID
     * @param array $task 任务信息
     * @return void
     */
    protected static function executeTask(string $id, array &$task): void
    {
        $startTime = microtime(true);
        $task['options']['last_run'] = time();
        $task['options']['attempts']++;

        $historyEntry = [
            'id' => $id,
            'name' => $task['name'],
            'start_time' => $startTime,
            'end_time' => null,
            'status' => 'running',
            'message' => '',
            'runtime' => 0,
        ];


        array_unshift(self::$history, $historyEntry);
        $historyIndex = 0;

        go(static function () use ($id, $task, $startTime, $historyIndex) {
            try {
                call_user_func($task['callback']);

                $endTime = microtime(true);
                $runtime = $endTime - $startTime;

                self::$history[$historyIndex]['end_time'] = $endTime;
                self::$history[$historyIndex]['status'] = 'success';
                self::$history[$historyIndex]['runtime'] = $runtime;

                Output::info(
                    '[cron]',
                    sprintf(
                        '[%s] Task "%s" completed successfully in %.4f seconds',
                        date('Y/m/d H:i:s'),
                        $task['name'],
                        $runtime
                    )
                );
            } catch (Throwable $exception) {
                $endTime = microtime(true);
                $runtime = $endTime - $startTime;

                self::$history[$historyIndex]['end_time'] = $endTime;
                self::$history[$historyIndex]['status'] = 'error';
                self::$history[$historyIndex]['message'] = $exception->getMessage();
                self::$history[$historyIndex]['runtime'] = $runtime;

                Output::error(
                    '[cron]',
                    sprintf(
                        '[%s] Task "%s" failed: %s (%.4f seconds)',
                        date('Y/m/d H:i:s'),
                        $task['name'],
                        $exception->getMessage(),
                        $runtime
                    )
                );
            }
        });
    }

    /**
     * @param Manager $manager
     * @return void
     */
    public function register(Manager $manager): void
    {
        Output::info('[Coral]', 'Cron system registered successfully!');
    }

    /**
     * @return void
     */
    public function onReload(): void
    {
        foreach (array_keys(self::$tasks) as $id) {
            self::calculateNextRunTime($id);
        }

        Output::info('[Coral]', 'Cron system reloaded!');
    }
}
