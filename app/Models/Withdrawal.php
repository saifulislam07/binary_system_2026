<?php

namespace App\Models;

use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use Carbon\CarbonInterface;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status changes only through WithdrawalService (Phase 7).
 *
 * @property WithdrawalMethodType $method
 * @property WithdrawalStatus $status
 * @property array<string, string> $account_details
 * @property CarbonInterface|null $processed_at
 */
#[Fillable(['member_id', 'amount', 'method', 'account_details'])]
class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => WithdrawalMethodType::class,
            'account_details' => 'array',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * The pending wallet debit holding the funds.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function holdTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
