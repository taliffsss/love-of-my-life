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
 * @property string $status active, deactivated or suspended: only an active account can sign in
 * @property ?string $status_reason
 * @property ?\DateTimeInterface $tour_seen_at when the dashboard tour was finished or skipped (null: not yet, so it shows)
 * @property string $login_type manual, google or facebook: how the account was made
 */
final class User extends Model
{
    public const ACTIVE = 'active';
    public const DEACTIVATED = 'deactivated';
    public const SUSPENDED = 'suspended';
    public const STATUSES = [self::ACTIVE, self::DEACTIVATED, self::SUSPENDED];

    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['password' => 'hashed', 'is_admin' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'status_changed_at' => 'datetime', 'tour_seen_at' => 'datetime'];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** What a person who cannot sign in is told. */
    public function inactiveMessage(): string
    {
        if ($this->status === self::SUSPENDED) {
            $why = trim((string) $this->status_reason);
            return 'This account is suspended' . ($why !== '' ? ': ' . $why : '.') . ' Contact support if you think this is a mistake.';
        }
        return 'This account has been deactivated. Contact support to have it reactivated.';
    }

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
