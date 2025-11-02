<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Domain-level exception intended to surface user-facing error messages with
 * a severity level and optional context payload.
 */
class NotifyErrorException extends RuntimeException
{
    /**
     * @param  string  $message  Human readable message to return to the client
     * @param  string  $level  Severity level: error|warning|info|success
     * @param  int  $status  HTTP status code to return
     * @param  array  $context  Optional structured context for logging/response
     */
    public function __construct(
        string $message,
        private readonly string $level = 'error',
        private readonly int $status = HttpResponse::HTTP_BAD_REQUEST,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Create a new error-level exception.
     */
    public static function error(string $message, array $context = [], int $status = HttpResponse::HTTP_BAD_REQUEST, ?Throwable $previous = null): self
    {
        return new self($message, 'error', $status, $context, $previous);
    }

    /**
     * Create a new warning-level exception.
     */
    public static function warning(string $message, array $context = [], int $status = HttpResponse::HTTP_BAD_REQUEST, ?Throwable $previous = null): self
    {
        return new self($message, 'warning', $status, $context, $previous);
    }

    /**
     * Create a new informational exception.
     */
    public static function info(string $message, array $context = [], int $status = HttpResponse::HTTP_OK, ?Throwable $previous = null): self
    {
        return new self($message, 'info', $status, $context, $previous);
    }

    /**
     * Create a new success-level exception.
     */
    public static function success(string $message, array $context = [], int $status = HttpResponse::HTTP_OK, ?Throwable $previous = null): self
    {
        return new self($message, 'success', $status, $context, $previous);
    }

    /**
     * Severity level accessor.
     */
    public function level(): string
    {
        return $this->level;
    }

    /**
     * HTTP status accessor.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Structured context payload accessor.
     */
    public function context(): array
    {
        return $this->context;
    }
}
