<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use App\Models\User;
use App\Services\EmailVerification;
use App\Services\PhotoStorage;
use Naluz\Auth\Auth;
use Naluz\Security\Hasher;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Storage\StorageException;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/** The account's own profile, for every account (a person who builds pages, or an administrator): who they are, how to reach them, their photo and password. */
final class AccountController
{
    /** A profile photo can be up to 10 MB. */
    public const AVATAR_MAX_BYTES = 10 * 1024 * 1024;

    private const ADDRESS = ['address_line1' => 120, 'address_line2' => 120, 'city' => 80, 'region' => 80, 'postal_code' => 20, 'country' => 60];

    public function show(Auth $auth, PhotoStorage $storage, EmailVerification $verification): ResponseInterface
    {
        $user = $this->me($auth);
        return Screen::render('account.profile', [
            'section' => 'profile',
            'avatarUrl' => $storage->url($user->avatar_disk, $user->avatar_path),
            'confirmationOn' => $verification->enabled(),
            'maxAvatar' => PhotoStorage::megabytes(self::AVATAR_MAX_BYTES),
            'maxAvatarBytes' => self::AVATAR_MAX_BYTES,
        ]);
    }

    public function update(ServerRequestInterface $request, Auth $auth, EmailVerification $verification): ResponseInterface
    {
        $user = $this->me($auth);
        $body = Request::body($request);
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($body['name'] ?? '')) ?? '');
        $email = User::normaliseEmail((string) ($body['email'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));
        $address = [];
        foreach (self::ADDRESS as $field => $max) {
            $address[$field] = trim((string) ($body[$field] ?? ''));
        }

        $errors = [];
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = ['Enter your name (up to 80 characters).'];
        }
        if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ['Enter a valid e-mail address.'];
        } elseif ($email !== $user->email && User::query()->withoutCache()->useWritePdo()->withTrashed()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $errors['email'] = ['Another account already has this e-mail.'];
        }
        if ($phone !== '' && preg_match('/^\+?[0-9][0-9 ().\-]{5,30}$/', $phone) !== 1) {
            $errors['phone'] = ['Use digits only, with an optional + at the start (for example +63 917 123 4567).'];
        }
        foreach (self::ADDRESS as $field => $max) {
            if (mb_strlen($address[$field]) > $max) {
                $errors[$field] = ["Use up to {$max} characters."];
            }
        }
        if ($address['postal_code'] !== '' && preg_match('/^[A-Za-z0-9 \-]{2,20}$/', $address['postal_code']) !== 1) {
            $errors['postal_code'] = ['Use letters, numbers, spaces and dashes only.'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['name' => $name, 'email' => $email, 'phone' => $phone] + $address);
        }

        $changes = ['name' => $name, 'phone' => $phone !== '' ? $phone : null];
        foreach ($address as $field => $value) {
            $changes[$field] = $value !== '' ? $value : null;
        }
        $message = 'Profile saved.';
        if ($email === $user->email) {
            $changes['pending_email'] = null; // asking for the address it already has cancels a change that was waiting
        } elseif ($verification->enabled()) {
            // the sign-in e-mail only changes once the new address proves it is theirs
            $changes['pending_email'] = $email;
            $user->forceFill($changes)->save();
            $sent = $verification->send($user);
            return Screen::back($sent ? '/email/verify' : '/account', $sent
                ? 'Profile saved. We sent a 6-digit code to ' . $email . ': enter it to change your sign-in e-mail.'
                : 'Profile saved, but we could not send the confirmation to ' . $email . '. Try saving again in a minute.');
        } else {
            $changes['email'] = $email;
            $changes['pending_email'] = null;
            $message = 'Profile saved. Your e-mail is now ' . $email . '.';
        }
        $user->forceFill($changes)->save();
        return Screen::back('/account', $message);
    }

    public function password(ServerRequestInterface $request, Auth $auth, Hasher $hasher): ResponseInterface
    {
        $user = $this->me($auth);
        $body = Request::body($request);
        $current = (string) ($body['current_password'] ?? '');
        $new = (string) ($body['password'] ?? '');
        $errors = [];
        // a Google or Facebook account has a password nobody knows: it may set one without giving the old one
        if ($user->login_type === 'manual' && !$hasher->check($current, (string) $user->getRawAttributes()['password'])) {
            $errors['current_password'] = ['That is not your current password.'];
        }
        if (mb_strlen($new) < 8 || mb_strlen($new) > 200) {
            $errors['password'] = ['Use at least 8 characters.'];
        } elseif ($new !== (string) ($body['password_confirmation'] ?? '')) {
            $errors['password'] = ['The two passwords do not match.'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, []);
        }
        $user->forceFill(['password' => $new])->save(); // hashed by the model cast
        return Screen::back('/account', 'Password changed.');
    }

    public function avatar(ServerRequestInterface $request, Auth $auth, PhotoStorage $storage): ResponseInterface
    {
        $user = $this->me($auth);
        $file = $request->getUploadedFiles()['avatar'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            // a body over PHP's post_max_size arrives without its files
            $length = (int) $request->getHeaderLine('Content-Length');
            throw new ValidationException(['avatar' => [$length > self::AVATAR_MAX_BYTES ? self::tooLarge($length) : 'Choose a photo first.']], []);
        }
        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['avatar' => ['Choose a photo first.']], []);
        }
        $size = (int) $file->getSize();
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $size > self::AVATAR_MAX_BYTES) {
            throw new ValidationException(['avatar' => [self::tooLarge($size)]], []);
        }
        try {
            $stored = $storage->storeFor($file, $user->id, 'avatar', self::AVATAR_MAX_BYTES);
        } catch (StorageException $e) {
            throw new ValidationException(['avatar' => [$e->getMessage()]], []);
        }
        [$oldDisk, $oldPath] = [(string) $user->avatar_disk, (string) $user->avatar_path];
        try {
            $user->forceFill(['avatar_disk' => $stored['disk'], 'avatar_path' => $stored['path']])->save();
        } catch (\Throwable $e) {
            $storage->discard($stored['disk'], $stored['path']);
            throw $e;
        }
        if ($oldPath !== '') {
            $storage->discard($oldDisk, $oldPath); // the replaced photo's file goes; a file shared with a page photo stays
        }
        return Screen::back('/account', 'Profile photo updated.');
    }

    public function removeAvatar(Auth $auth, PhotoStorage $storage): ResponseInterface
    {
        $user = $this->me($auth);
        if ((string) $user->avatar_path !== '') {
            [$disk, $path] = [(string) $user->avatar_disk, (string) $user->avatar_path];
            $user->forceFill(['avatar_disk' => null, 'avatar_path' => null])->save();
            $storage->discard($disk, $path);
        }
        return Screen::back('/account', 'Profile photo removed.');
    }

    /** "This photo is 14.2 MB. A profile photo can be up to 10 MB." */
    public static function tooLarge(int $bytes): string
    {
        $max = 'A profile photo can be up to ' . PhotoStorage::megabytes(self::AVATAR_MAX_BYTES) . '.';
        return $bytes > self::AVATAR_MAX_BYTES ? 'This photo is ' . PhotoStorage::megabytes($bytes) . '. ' . $max : 'This photo is too large. ' . $max;
    }

    private function me(Auth $auth): User
    {
        $user = $auth->user();
        if (!$user instanceof User) {
            throw new HttpException(401, 'Sign in first.');
        }
        return $user;
    }
}
