<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Naluz\Auth\Auth;
use Naluz\Http\HttpException;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** The admin area: someone signed out goes to sign in; anyone who is not an administrator gets a plain 404, as if it were not there. */
final class EnsureAdmin implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            return Response::redirect('/login');
        }
        if (!$user->is_admin) {
            throw new HttpException(404, 'Not found.');
        }

        return $handler->handle($request)->withHeader('X-Robots-Tag', 'noindex, nofollow')->withHeader('Cache-Control', 'private, no-store');
    }
}
