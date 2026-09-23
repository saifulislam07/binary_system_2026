<?php

namespace App\Models;

use App\Enums\PlacementSide;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sale's BV as it sits on one upline ancestor's side (centi-BV).
 * Only TeamVolumeService / MatchingService / CommissionReversalService touch these.
 *
 * @property PlacementSide $side
 */
#[Fillable(['member_id', 'side', 'sale_id', 'bv', 'remaining'])]
class VolumeLot extends Model
{
    protected function casts(): array
    {
        return [
            'side' => PlacementSide::class,
            'bv' => 'integer',
            'remaining' => 'integer',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return HasMany<VolumeConsumption, $this> */
    public function consumptions(): HasMany
    {
        return $this->hasMany(VolumeConsumption::class);
    }
}
