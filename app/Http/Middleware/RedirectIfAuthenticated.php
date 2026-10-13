<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Naluz\Auth\Auth;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** The sign-in and sign-up pages send someone who is already signed in straight to the dashboard. */
final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->auth->check() ? Response::redirect('/dashboard') : $handler->handle($request);
    }
}
