<?php

namespace App\Models;

use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CommissionType $type
 * @property PayoutStatus $status
 */
#[Fillable(['member_id', 'type', 'source_sale_id', 'commission_cycle_id', 'amount', 'cycle_date', 'status', 'description'])]
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
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

    /** @return BelongsTo<Sale, $this> */
    public function sourceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'source_sale_id');
    }

    /** @return BelongsTo<CommissionCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(CommissionCycle::class, 'commission_cycle_id');
    }
}
