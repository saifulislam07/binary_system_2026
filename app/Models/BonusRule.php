<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A one-time threshold bonus. type `leadership`: threshold = active team
 * members; type `sales`: threshold = personal sales in poysha.
 */
#[Fillable(['type', 'name', 'threshold', 'amount', 'is_active'])]
class BonusRule extends Model
{
    public const LEADERSHIP = 'leadership';

    public const SALES = 'sales';

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Bonus, $this> */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }
}
