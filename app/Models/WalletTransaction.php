<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger row. amount is positive poysha; direction gives the sign.
 *
 * @property WalletTransactionType $type
 * @property TransactionDirection $direction
 * @property WalletTransactionStatus $status
 */
#[Fillable([
    'wallet_id', 'type', 'direction', 'amount', 'balance_after',
    'reference_type', 'reference_id', 'description', 'status',
])]
class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'direction' => TransactionDirection::class,
            'status' => WalletTransactionStatus::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The signed effect of this row on the balance.
     */
    public function signedAmount(): int
    {
        return $this->direction === TransactionDirection::Credit ? $this->amount : -$this->amount;
    }
}
