<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\SoftDeletes;
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
 * @property ?\DateTimeInterface $editor_tour_seen_at the same for the page editor's tour
 * @property ?string $intended_plan the plan picked on the pricing section before signing up (App\Services\IntendedPlan)
 * @property ?string $utm_source first-touch campaign tags saved at sign-up (App\Services\Traffic)
 * @property ?string $utm_medium
 * @property ?string $utm_campaign
 * @property ?string $utm_content
 * @property ?\DateTimeInterface $deleted_at when the account was deleted (null: not deleted)
 * @property string $login_type manual, google or facebook: how the account was made
 * @property ?\DateTimeInterface $email_verified_at when the e-mail was confirmed (null: not yet)
 * @property ?string $pending_email an address asked for in the profile, not confirmed yet
 * @property ?string $phone
 * @property ?string $address_line1
 * @property ?string $address_line2
 * @property ?string $city
 * @property ?string $region
 * @property ?string $postal_code
 * @property ?string $country
 * @property ?string $avatar_disk the profile photo's disk and key (App\Services\PhotoStorage makes its URL)
 * @property ?string $avatar_path
 */
final class User extends Model
{
    use SoftDeletes; // deleting an account only hides it (App\Services\AccountRemoval); bin/trash.php restores it

    public const ACTIVE = 'active';
    public const DEACTIVATED = 'deactivated';
    public const SUSPENDED = 'suspended';
    public const DELETED_MESSAGE = 'This account was deleted. Contact support if you would like it restored.';
    public const STATUSES = [self::ACTIVE, self::DEACTIVATED, self::SUSPENDED];

    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password', 'verify_code_hash', 'reset_code_hash'];
    protected array $casts = ['password' => 'hashed', 'is_admin' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'status_changed_at' => 'datetime', 'tour_seen_at' => 'datetime', 'editor_tour_seen_at' => 'datetime', 'deleted_at' => 'datetime', 'email_verified_at' => 'datetime', 'verify_expires_at' => 'datetime', 'verify_sent_at' => 'datetime', 'reset_expires_at' => 'datetime', 'reset_sent_at' => 'datetime', 'verify_attempts' => 'integer', 'reset_attempts' => 'integer'];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** The profile's postal address as one line ("12 Rose St, Quezon City, Metro Manila 1100, Philippines"), or '' when none is filled in. */
    public function addressLine(): string
    {
        $locality = trim(implode(' ', array_filter([(string) $this->region, (string) $this->postal_code], static fn (string $v): bool => $v !== '')));
        return implode(', ', array_filter([(string) $this->address_line1, (string) $this->address_line2, (string) $this->city, $locality, (string) $this->country], static fn (string $v): bool => trim($v) !== ''));
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
