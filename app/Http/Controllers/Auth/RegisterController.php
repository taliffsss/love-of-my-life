<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Models\User;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\Request;
use Naluz\Validation\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegisterController
{
    public function show(): ResponseInterface
    {
        return Screen::render('auth.register');
    }

    public function store(ServerRequestInterface $request, Auth $auth, Connection $db): ResponseInterface
    {
        $input = Request::body($request);
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'email' => User::normaliseEmail((string) ($input['email'] ?? '')),
            'password' => (string) ($input['password'] ?? ''),
            'password_confirmation' => (string) ($input['password_confirmation'] ?? ''),
        ];
        Validator::make($data, [
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => 'required|string|min:8|max:200|confirmed',
        ], [
            'email.unique' => 'There is already an account with this e-mail. Sign in instead.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Use at least 8 characters.',
            'name.required' => 'Tell us your name.',
        ], $db)->validate();

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model cast
            'is_admin' => false,
        ])->save();

        $auth->login($user);
        return Screen::back('/dashboard', 'Welcome, ' . $user->name . '! Create your first page below.');
    }
}
