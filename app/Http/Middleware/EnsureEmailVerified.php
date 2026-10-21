<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\EmailVerification;
use Naluz\Auth\Auth;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** The dashboard and the admin area need a confirmed e-mail address (when confirmation is on): anyone else is sent to ask for the link again. */
final class EnsureEmailVerified implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly EmailVerification $verification)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();
        if ($user instanceof User && $this->verification->required($user)) {
            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return Response::json(['message' => 'Confirm your e-mail address first.', 'code' => 'email_unconfirmed'], 403);
            }
            return Response::redirect('/email/verify', 303);
        }
        return $handler->handle($request);
    }
}
