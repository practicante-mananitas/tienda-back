<?php
use App\Http\Middleware\DisableCsrf;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append([
            HandleCors::class,
            
            //DisableCsrf::class, // 👈 Aquí registras el middleware nuevo
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

