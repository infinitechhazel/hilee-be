<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    
    ->withMiddleware(function (Middleware $middleware) {
        // Exclude API routes from CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    
        // Configure API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // ADD THIS: Handle unauthenticated API requests
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                // For API routes, don't redirect - return null to trigger JSON response
                return null;
            }
            return route('login'); // For web routes (if you have any)
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
