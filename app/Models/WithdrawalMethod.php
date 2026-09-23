<?php

namespace App\Models;

use App\Enums\WithdrawalMethodType;
use Database\Factories\WithdrawalMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property WithdrawalMethodType $type
 */
#[Fillable(['member_id', 'type', 'details', 'is_default'])]
class WithdrawalMethod extends Model
{
    /** @use HasFactory<WithdrawalMethodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WithdrawalMethodType::class,
            'details' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
