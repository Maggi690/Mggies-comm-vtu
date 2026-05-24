<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation', 'transaction_pin', 'pin'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Add custom reporting logic here (e.g., Sentry, Bugsnag)
        });
    }

    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Throwable $e): JsonResponse
    {
        // Validation errors
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Authentication
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login.',
            ], 401);
        }

        // Authorization (Spatie)
        if ($e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        // Not found
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'message' => "{$model} not found.",
            ], 404);
        }

        // HTTP exceptions
        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP Error ' . $e->getStatusCode(),
            ], $e->getStatusCode());
        }

        // Domain exceptions
        if ($e instanceof AuthException) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 401);
        }
        if ($e instanceof InsufficientBalanceException) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($e instanceof InvalidPinException) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($e instanceof VtuException) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($e instanceof PaymentException) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($e instanceof NoProviderAvailableException) {
            return response()->json(['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.'], 503);
        }

        // Generic server error — don't leak stack traces in production
        $message = config('app.debug') ? $e->getMessage() : 'An internal server error occurred.';

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}
