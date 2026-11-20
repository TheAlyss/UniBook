<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API gateway: every /api request is JSON.
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Uniform error format for the API: { success: false, message, data }
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // website keeps Laravel's normal error pages
            }

            [$status, $message, $data] = match (true) {
                $e instanceof ValidationException => [422, $e->getMessage(), ['errors' => $e->errors()]],
                $e instanceof AuthenticationException => [401, 'Please log in first. Send your token as "Authorization: Bearer <token>".', null],
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => [403, $e->getMessage() ?: 'You are not allowed to do this.', null],
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => [404, 'The requested item was not found.', null],
                $e instanceof ThrottleRequestsException => [429, 'Too many requests. Please wait a minute and try again.', null],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Request failed.', null],
                default => [500, config('app.debug') ? $e->getMessage() : 'Something went wrong on our side.', null],
            };

            return response()->json(['success' => false, 'message' => $message, 'data' => $data], $status);
        });
    })->create();
