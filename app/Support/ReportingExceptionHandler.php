<?php

declare(strict_types=1);

namespace App\Support;

use Naluz\Foundation\ExceptionHandler;
use Naluz\Http\HttpException;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The framework's exception handler, which also reports server errors (5xx) to Sentry. The response is exactly the framework's. */
class ReportingExceptionHandler extends ExceptionHandler
{
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $status = $e instanceof HttpException ? $e->status() : 500;
        if (!$e instanceof ValidationException && $status >= 500) {
            $this->report($e);
        }
        return parent::render($e, $request);
    }

    /** Sends the error to Sentry; does nothing when Sentry is not started. */
    protected function report(\Throwable $e): void
    {
        try {
            \Sentry\captureException($e);
        } catch (\Throwable) {
            // reporting must never break the response
        }
    }
}
