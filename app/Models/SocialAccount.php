<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\BelongsTo;

/**
 * A Google or Facebook account linked to a user. Identified by the provider's own id (`provider_uid`), never by e-mail.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_uid
 */
final class SocialAccount extends Model
{
    protected array $casts = ['user_id' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
