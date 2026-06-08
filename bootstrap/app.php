<?php

use Elephant\Response\ThrowableReport;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(['api/*']);
        $middleware->statefulApi();
        $middleware->throttleWithRedis();
        $middleware->redirectGuestsTo(fn(): Response => response([
            'msg' => '无效令牌访问',
            'code' => 401,
            'data' => null,
        ]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 当无Token访问时，会抛出该异常，但是在自定义的中间件中无法捕获，只能在这捕获并抛出JSON响应
        $exceptions->renderable(function (AuthenticationException $e) {
            return new JsonResponse(
                app(ThrowableReport::class)->report($e),
            );
        });
    })->create();
