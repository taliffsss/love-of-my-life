<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Models\User;
use App\Services\IntendedPlan;
use App\Services\Seasons;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\Request;
use Naluz\Security\Hasher;
use Naluz\Session\Store;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionController
{
    public function show(ServerRequestInterface $request, IntendedPlan $intended, Store $session, Seasons $seasons): ResponseInterface
    {
        $intended->remember($session, $request->getQueryParams()['plan'] ?? null);
        return Screen::render('auth.login', ['theme' => $seasons->look($request->getQueryParams())]);
    }

    public function store(ServerRequestInterface $request, Auth $auth, Connection $db, IntendedPlan $intended, Store $session, Hasher $hasher): ResponseInterface
    {
        $input = Request::body($request);
        $email = User::normaliseEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '' || !$auth->attempt(['email' => $email, 'password' => $password])) {
            // a deleted account is hidden, not gone: once the password is right it is told so, instead of "do not match" (which would be false)
            $deleted = $email === '' ? null : User::query()->onlyTrashed()->where('email', $email)->first();
            if ($deleted instanceof User && $hasher->check($password, (string) $deleted->getRawAttributes()['password'])) {
                throw new ValidationException(['email' => [User::DELETED_MESSAGE]], ['email' => $email]);
            }
            // one message for "no such account" and "wrong password", so the form does not reveal who has an account
            throw new ValidationException(['email' => ['That e-mail and password do not match an account.']], ['email' => $email]);
        }
        // the password was right, so it is safe to say why this account cannot come in
        $user = $auth->user();
        if ($user instanceof User && !$user->isActive()) {
            $auth->logout();
            throw new ValidationException(['email' => [$user->inactiveMessage()]], ['email' => $email]);
        }

        if ($user instanceof User) {
            $intended->claim($session, $user, $db);
        }
        return Screen::back('/dashboard');
    }

    public function destroy(Auth $auth): ResponseInterface
    {
        $auth->logout();
        return Screen::back('/', 'You are signed out.');
    }
}
