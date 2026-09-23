<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ExpenseCategory $category
 */
#[Fillable(['category', 'amount', 'description', 'date', 'recorded_by'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
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
