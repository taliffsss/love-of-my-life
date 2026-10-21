<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Screen;
use App\Models\Page;
use App\Models\User;
use App\Services\AccountRemoval;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An administrator's actions on one account: edit its details, deactivate, suspend or reactivate it, delete it. Never on
 * yourself (you would lock yourself out) and never a status change on another administrator (remove the role first). Each
 * action is written to the log with who did it.
 */
final class UsersController
{
    public function edit(string $user, Auth $auth, Connection $db): ResponseInterface
    {
        $target = $this->find($user);
        return Screen::render('admin.user-edit', [
            'target' => $target,
            'pageCount' => Page::query()->where('user_id', $target->id)->count(),
            'paidOrders' => $db->table('orders')->where('user_id', '=', $target->id)->where('status', '=', 'paid')->count(),
            'self' => $target->id === (int) $auth->id(),
        ]);
    }

    public function update(string $user, ServerRequestInterface $request, Auth $auth, Connection $db): ResponseInterface
    {
        $target = $this->find($user);
        $body = Request::body($request);
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($body['name'] ?? '')) ?? '');
        $email = User::normaliseEmail((string) ($body['email'] ?? ''));
        $admin = !empty($body['is_admin']);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = ['Enter a name of up to 80 characters.'];
        }
        if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ['Enter a valid e-mail address.'];
        } elseif ($db->table('users')->where('email', '=', $email)->where('id', '!=', $target->id)->count() > 0) {
            $errors['email'] = ['Another account already has this e-mail.'];
        }
        if ($admin !== $target->is_admin) {
            if ($target->id === (int) $auth->id()) {
                $errors['is_admin'] = ['You cannot change your own administrator role.'];
            } elseif (!$admin && $db->table('users')->where('is_admin', '=', true)->where('id', '!=', $target->id)->where('status', '=', User::ACTIVE)->count() === 0) {
                $errors['is_admin'] = ['There must be at least one active administrator.'];
            } elseif ($admin && !$target->isActive()) {
                $errors['is_admin'] = ['Reactivate the account before making it an administrator.'];
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['name' => $name, 'email' => $email]);
        }

        $target->forceFill(['name' => $name, 'email' => $email, 'is_admin' => $admin])->save();
        logger()->info('[admin] {admin} edited user {user}', ['admin' => $auth->id(), 'user' => $target->id]);
        return Screen::back($this->editUrl($target), 'Saved.');
    }

    /** Body: status (active, deactivated, suspended) and, for a suspension, reason (optional, shown to the person). */
    public function status(string $user, ServerRequestInterface $request, Auth $auth): ResponseInterface
    {
        $target = $this->find($user);
        $body = Request::body($request);
        $status = (string) ($body['status'] ?? '');
        if (!in_array($status, User::STATUSES, true)) {
            throw new ValidationException(['status' => ['Choose active, deactivated or suspended.']]);
        }
        if ($target->id === (int) $auth->id()) {
            throw new ValidationException(['status' => ['You cannot change your own account status.']]);
        }
        if ($target->is_admin && $status !== User::ACTIVE) {
            throw new ValidationException(['status' => ['Remove the administrator role before deactivating or suspending this account.']]);
        }
        $reason = $status === User::SUSPENDED ? mb_substr(trim((string) ($body['reason'] ?? '')), 0, 200) : '';

        $target->forceFill([
            'status' => $status,
            'status_reason' => $reason !== '' ? $reason : null,
            'status_changed_at' => gmdate('Y-m-d H:i:s'),
        ])->save();
        logger()->info('[admin] {admin} set user {user} to {status}', ['admin' => $auth->id(), 'user' => $target->id, 'status' => $status]);

        $done = ['active' => 'reactivated', 'deactivated' => 'deactivated', 'suspended' => 'suspended'][$status];
        return Screen::back($this->returnTo($request, $target), $target->name . ' was ' . $done . '.');
    }

    /** Body: confirm = "delete". */
    public function destroy(string $user, ServerRequestInterface $request, Auth $auth, AccountRemoval $removal): ResponseInterface
    {
        $target = $this->find($user);
        if ((Request::body($request)['confirm'] ?? '') !== 'delete') {
            throw new HttpException(422, 'Confirm that the account should be deleted.');
        }
        if ($target->id === (int) $auth->id()) {
            throw new ValidationException(['confirm' => ['You cannot delete your own account here.']]);
        }
        if (($why = $removal->blocker($target)) !== null) {
            return Screen::back($this->returnTo($request, $target), $why);
        }

        $gone = $removal->delete($target);
        logger()->warning('[admin] {admin} deleted user {user} ({pages} pages, {photos} photos)', ['admin' => $auth->id(), 'user' => $target->id] + $gone);
        return Screen::back('/admin/users', $target->name . ' was deleted (hidden, not erased)' . ($gone['pages'] > 0 ? ' with ' . $gone['pages'] . ' page' . ($gone['pages'] === 1 ? '' : 's') : '') . '.');
    }

    private function find(string $id): User
    {
        $user = ctype_digit($id) ? User::query()->find((int) $id) : null;
        if (!$user instanceof User) {
            throw new HttpException(404, 'User not found.');
        }
        return $user;
    }

    private function editUrl(User $user): string
    {
        return '/admin/users/' . $user->id . '/edit';
    }

    /** Back to the list the action came from (a local /admin/ path only), or the user's own page. */
    private function returnTo(ServerRequestInterface $request, User $user): string
    {
        $to = (string) (Request::body($request)['return'] ?? '');
        return str_starts_with($to, '/admin/') && !str_contains($to, '//') && !str_contains($to, "\n") ? $to : $this->editUrl($user);
    }
}
