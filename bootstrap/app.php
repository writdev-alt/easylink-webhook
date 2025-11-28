<?php

use App\Exceptions\NotifyErrorException;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: null,
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: env('API_PREFIX', 'api'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(replace: [
            ValidateCsrfToken::class => VerifyCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $shouldRespondWithJson = static function (Request $request): bool {
            return $request->expectsJson()
                || $request->wantsJson()
                || $request->is('ipn/*');
        };

        $exceptions->render(function (NotifyErrorException $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            $payload = [
                'status' => $exception->level(),
                'message' => $exception->getMessage(),
            ];

            if ($context = $exception->context()) {
                $payload['context'] = $context;
            }

            return response()->json($payload, $exception->status());
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => __('The given data was invalid.'),
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => __('Authentication required.'),
            ], HttpResponse::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage() ?: __('You are not authorized to perform this action.'),
            ], HttpResponse::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => __('Resource not found.'),
            ], HttpResponse::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($shouldRespondWithJson) {
            if (! $shouldRespondWithJson($request)) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : HttpResponse::HTTP_INTERNAL_SERVER_ERROR;

            $message = $status >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR
                ? __('Something went wrong on our end.')
                : $exception->getMessage();

            $payload = [
                'status' => $status >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR ? 'error' : 'warning',
                'message' => $message,
            ];

            if (config('app.debug')) {
                $payload['exception'] = class_basename($exception);
                $payload['trace'] = collect($exception->getTrace())->take(5);
            }

            return response()->json($payload, $status);
        });
    })->create();
