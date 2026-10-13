<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\HasMany;

/**
 * Someone who builds pages. Signs in with e-mail and password (Naluz\Auth\Auth, session based).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
final class User extends Model
{
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['password' => 'hashed', 'is_admin' => 'boolean'];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /** E-mail addresses are compared case-insensitively: they are stored trimmed and lower-cased. */
    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
