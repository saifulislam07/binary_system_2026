<?php

namespace App\Models;

use Database\Factories\TeamVolumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-member matching snapshot for one commission cycle.
 * Volumes are centi-BV; commission columns are poysha.
 *
 * left/right_volume: available at matching time. carried_*: left over and
 * kept for the next cycle. flushed_*: discarded (carry-forward disabled).
 * gross_commission = matched × rate; paid_commission is the part paid this
 * cycle, overflow_commission the part above the caps (voided or deferred).
 */
#[Fillable([
    'member_id', 'commission_cycle_id', 'left_volume', 'right_volume', 'matched_volume',
    'carried_left', 'carried_right', 'flushed_left', 'flushed_right', 'carry_forward_enabled',
    'gross_commission', 'paid_commission', 'overflow_commission', 'overflow_action', 'deferred_released',
])]
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
            'flushed_left' => 'integer',
            'flushed_right' => 'integer',
            'carry_forward_enabled' => 'boolean',
            'gross_commission' => 'integer',
            'paid_commission' => 'integer',
            'overflow_commission' => 'integer',
            'deferred_released' => 'integer',
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

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
