<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Naluz\Auth\Auth;
use Naluz\Http\Response;
use Naluz\Session\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A deactivated or suspended account is signed out on its next request, wherever it is signed in. Runs after the session
 * starts, on every web route.
 */
final class EnsureActiveAccount implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly Store $session)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();
        if ($user instanceof User && !$user->isActive()) {
            $message = $user->inactiveMessage();
            $this->auth->logout();
            $this->session->flash('flash', $message);
            return Response::redirect('/login', 303);
        }

        return $handler->handle($request);
    }
}
