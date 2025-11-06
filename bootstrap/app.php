<?php

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
        // Register CORS middleware globally
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Ensure API endpoints remain stateless (no CSRF)
        // This prevents 419 errors if a POST to /api accidentally hits web group
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Admin Basic Auth alias removed per request; admin route is now public
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ...
    })->create();