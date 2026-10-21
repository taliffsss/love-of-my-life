<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Models\User;
use App\Services\PasswordReset;
use App\Services\Seasons;
use Naluz\Http\Request;
use Naluz\Session\Store;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Forgot password: ask for a code by e-mail, then enter it with a new password. */
final class PasswordResetController
{
    public function forgot(ServerRequestInterface $request, Seasons $seasons): ResponseInterface
    {
        return Screen::render('auth.forgot', ['theme' => $seasons->look($request->getQueryParams())]);
    }

    public function send(ServerRequestInterface $request, PasswordReset $reset, Store $session): ResponseInterface
    {
        $email = User::normaliseEmail((string) (Request::body($request)['email'] ?? ''));
        if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['email' => ['Enter the e-mail address of your account.']], ['email' => $email]);
        }
        $reset->request($email);
        $session->put('reset_email', $email); // so the next screen already knows it
        // the same answer whether or not there is an account, so the form cannot be used to find out who has one
        return Screen::back('/reset-password', 'If there is an account for ' . $email . ', we sent it a 6-digit code.');
    }

    public function form(ServerRequestInterface $request, Store $session, Seasons $seasons): ResponseInterface
    {
        return Screen::render('auth.reset', [
            'email' => (string) ($session->get('reset_email') ?? ''),
            'theme' => $seasons->look($request->getQueryParams()),
        ]);
    }

    public function reset(ServerRequestInterface $request, PasswordReset $reset, Store $session): ResponseInterface
    {
        $body = Request::body($request);
        $email = User::normaliseEmail((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $old = ['email' => $email];
        $errors = [];
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ['Enter the e-mail address of your account.'];
        }
        if (preg_match('/^\d{6}$/', \App\Services\OneTimeCode::clean((string) ($body['code'] ?? ''))) !== 1) {
            $errors['code'] = ['Enter the 6-digit code from the e-mail.']; // a slip of the keyboard does not use up one of the 5 tries
        }
        if (mb_strlen($password) < 8 || mb_strlen($password) > 200) {
            $errors['password'] = ['Use at least 8 characters.'];
        } elseif ($password !== (string) ($body['password_confirmation'] ?? '')) {
            $errors['password'] = ['The two passwords do not match.'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $old);
        }

        $result = $reset->reset($email, (string) $body['code'], $password);
        if ($result !== 'ok') {
            $message = [
                'invalid' => 'That code is not right. Check the e-mail and try again.',
                'locked' => 'Too many wrong tries. Ask for a new code.',
                'expired' => 'That code has expired or was not sent. Ask for a new one.',
            ][$result];
            throw new ValidationException(['code' => [$message]], $old);
        }
        $session->forget('reset_email');
        return Screen::back('/login', 'Your password is changed. Sign in with it.');
    }
}
