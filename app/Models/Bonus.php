<?php

namespace App\Models;

use App\Enums\BonusType;
use App\Enums\PayoutStatus;
use Database\Factories\BonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property BonusType $type
 * @property PayoutStatus $status
 */
#[Fillable(['member_id', 'bonus_rule_id', 'type', 'amount', 'cycle_date', 'status', 'description', 'awarded_by'])]
class Bonus extends Model
{
    /** @use HasFactory<BonusFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => BonusType::class,
            'status' => PayoutStatus::class,
            'amount' => 'integer',
            'cycle_date' => 'date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<BonusRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(BonusRule::class, 'bonus_rule_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'awarded_by');
    }
}
