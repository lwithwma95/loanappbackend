<?php

// Windows does not ship ext-pcntl. Define POSIX signal constants and stub
// the pcntl_* functions so Symfony Console's SignalRegistry and Laravel
// Octane can boot without crashing. Signal delivery won't be graceful on
// Windows, but the server starts and handles requests correctly.
if (!defined('SIGINT'))   define('SIGINT',   2);
if (!defined('SIGTERM'))  define('SIGTERM',  15);
if (!defined('SIGHUP'))   define('SIGHUP',   1);
if (!defined('SIGUSR1'))  define('SIGUSR1',  10);
if (!defined('SIGUSR2'))  define('SIGUSR2',  12);
if (!defined('SIGALRM'))  define('SIGALRM',  14);
if (!defined('SIGQUIT'))  define('SIGQUIT',  3);
if (!defined('SIG_DFL'))  define('SIG_DFL',  0);

if (!function_exists('pcntl_signal')) {
    function pcntl_signal(int $signal, $handler, bool $restart_syscalls = true): bool { return false; }
}
if (!function_exists('pcntl_signal_get_handler')) {
    function pcntl_signal_get_handler(int $signal): int|string { return SIG_DFL; }
}
if (!function_exists('pcntl_async_signals')) {
    function pcntl_async_signals(?bool $enable = null): bool { return false; }
}
if (!function_exists('pcntl_alarm')) {
    function pcntl_alarm(int $seconds): int { return 0; }
}
if (!function_exists('posix_kill')) {
    function posix_kill(int $pid, int $signal): bool {
        if ($signal === 0) {
            // Check process existence via tasklist
            $out = shell_exec("tasklist /FI \"PID eq {$pid}\" /NH 2>NUL");
            return str_contains($out ?? '', (string) $pid);
        }
        exec("taskkill /PID {$pid} /F 2>NUL");
        return true;
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();