<?php

namespace App\Models;

use Database\Factories\IncomeTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'amount', 'description', 'date', 'recorded_by'])]
class IncomeTransaction extends Model
{
    /** @use HasFactory<IncomeTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by');
    }
}
