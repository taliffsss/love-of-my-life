<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Models\User;
use Naluz\Auth\Auth;
use Naluz\Http\Request;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionController
{
    public function show(): ResponseInterface
    {
        return Screen::render('auth.login');
    }

    public function store(ServerRequestInterface $request, Auth $auth): ResponseInterface
    {
        $input = Request::body($request);
        $email = User::normaliseEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '' || !$auth->attempt(['email' => $email, 'password' => $password])) {
            // one message for "no such account" and "wrong password", so the form does not reveal who has an account
            throw new ValidationException(['email' => ['That e-mail and password do not match an account.']], ['email' => $email]);
        }
        return Screen::back('/dashboard');
    }

    public function destroy(Auth $auth): ResponseInterface
    {
        $auth->logout();
        return Screen::back('/', 'You are signed out.');
    }
}
