<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Models\User;
use App\Services\EmailVerification;
use App\Services\OneTimeCode;
use Naluz\Auth\Auth;
use Naluz\Http\Request;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Confirming an e-mail address with the code mailed to it: the code screen, entering the code, and sending a new one. */
final class EmailVerificationController
{
    public function notice(Auth $auth, EmailVerification $verification): ResponseInterface
    {
        $user = $auth->user();
        if (!$user instanceof User || !$verification->waiting($user)) {
            return Screen::back('/dashboard');
        }
        return Screen::render('auth.verify', [
            'address' => (string) ($user->pending_email ?: $user->email),
            'changing' => (bool) $user->pending_email,
            'canResend' => $verification->canResend($user),
        ]);
    }

    public function confirm(ServerRequestInterface $request, Auth $auth, EmailVerification $verification): ResponseInterface
    {
        $user = $auth->user();
        if (!$user instanceof User || !$verification->waiting($user)) {
            return Screen::back('/dashboard');
        }
        $typed = (string) (Request::body($request)['code'] ?? '');
        if (preg_match('/^\d{6}$/', OneTimeCode::clean($typed)) !== 1) {
            // a slip of the keyboard is not a wrong guess: it does not use up one of the 5 tries
            throw new ValidationException(['code' => ['Enter the 6-digit code from the e-mail.']], []);
        }
        $result = $verification->confirm($user, $typed);
        if ($result === 'ok') {
            return Screen::back('/dashboard', 'Your e-mail is confirmed.');
        }
        $errors = [
            'invalid' => 'That code is not right. Check the e-mail and try again.',
            'expired' => 'That code has expired. Ask for a new one below.',
            'locked' => 'Too many wrong tries. Ask for a new code below.',
            'taken' => 'That address now belongs to another account, so it was not changed.',
        ];
        if ($result === 'taken') {
            return Screen::back('/account', $errors['taken']);
        }
        throw new ValidationException(['code' => [$errors[$result]]], []);
    }

    public function resend(Auth $auth, EmailVerification $verification): ResponseInterface
    {
        $user = $auth->user();
        if (!$user instanceof User || !$verification->waiting($user)) {
            return Screen::back('/dashboard');
        }
        if (!$verification->canResend($user)) {
            return Screen::back('/email/verify', 'A code was sent a moment ago. Give it a minute, and check your spam folder.');
        }
        return Screen::back('/email/verify', $verification->send($user)
            ? 'We sent a new code to ' . ($user->pending_email ?: $user->email) . '.'
            : 'We could not send the e-mail right now. Try again in a minute.');
    }
}
