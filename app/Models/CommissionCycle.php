<?php

namespace App\Models;

use App\Enums\CommissionCycleStatus;
use Database\Factories\CommissionCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CommissionCycleStatus $status
 * @property Carbon $cycle_date
 * @property Carbon|null $closed_at
 */
#[Fillable(['cycle_date', 'status', 'closed_at'])]
class CommissionCycle extends Model
{
    /** @use HasFactory<CommissionCycleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cycle_date' => 'date',
            'status' => CommissionCycleStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<TeamVolume, $this> */
    public function teamVolumes(): HasMany
    {
        return $this->hasMany(TeamVolume::class);
    }

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
