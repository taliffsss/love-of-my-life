<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\BelongsTo;

/**
 * One photo of a page. `disk`/`path` say where the file is stored (both null for an imported photo that lives
 * elsewhere); `url` is what the page shows. Lower `position` plays first.
 *
 * @property int $id
 * @property int $page_id
 * @property ?string $disk
 * @property ?string $path
 * @property string $url
 * @property int $position
 */
final class Photo extends Model
{
    protected array $casts = ['page_id' => 'integer', 'position' => 'integer'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
