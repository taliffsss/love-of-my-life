<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\BelongsTo;
use Naluz\Database\Orm\SoftDeletes;

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
    use SoftDeletes; // a deleted photo is hidden and stops counting, its file stays (bin/trash.php restores or erases it)

    protected array $casts = ['page_id' => 'integer', 'position' => 'integer', 'deleted_at' => 'datetime'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
