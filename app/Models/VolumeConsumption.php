<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of a lot's remaining volume (centi-BV). Append-only.
 * kind: matched | flushed (by a cycle) | refunded (removed by a refund) |
 * restored / dissolved (a refund undid this matched pairing; `restored`
 * also gave the volume back to the lot). A matched row's effective amount is
 * bv − Σ(restored + dissolved rows pointing at it).
 */
#[Fillable(['volume_lot_id', 'team_volume_id', 'kind', 'bv', 'restores_consumption_id'])]
class VolumeConsumption extends Model
{
    public const MATCHED = 'matched';

    public const FLUSHED = 'flushed';

    public const REFUNDED = 'refunded';

    public const RESTORED = 'restored';

    public const DISSOLVED = 'dissolved';

    public const TRANSFERRED = 'transferred';

    protected function casts(): array
    {
        return [
            'bv' => 'integer',
        ];
    }

    /** @return BelongsTo<VolumeLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(VolumeLot::class, 'volume_lot_id');
    }

    /** @return BelongsTo<TeamVolume, $this> */
    public function teamVolume(): BelongsTo
    {
        return $this->belongsTo(TeamVolume::class);
    }
}
