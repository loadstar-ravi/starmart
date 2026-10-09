<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('home'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*') && $exception->getStatusCode() === 404) {
                return response()->json(['message' => 'The requested resource was not found.'], 404);
            }
        });
    })->create();
