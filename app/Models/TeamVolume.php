<?php

namespace App\Models;

use Database\Factories\TeamVolumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-member matching snapshot for one commission cycle (centi-BV).
 */
#[Fillable(['member_id', 'commission_cycle_id', 'left_volume', 'right_volume', 'matched_volume', 'carried_left', 'carried_right'])]
class TeamVolume extends Model
{
    /** @use HasFactory<TeamVolumeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'left_volume' => 'integer',
            'right_volume' => 'integer',
            'matched_volume' => 'integer',
            'carried_left' => 'integer',
            'carried_right' => 'integer',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<CommissionCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(CommissionCycle::class, 'commission_cycle_id');
    }
}
