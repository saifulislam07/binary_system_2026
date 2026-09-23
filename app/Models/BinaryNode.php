<?php

namespace App\Models;

use App\Enums\PlacementSide;
use Database\Factories\BinaryNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denormalized tree + volume row for one member. Volumes are centi-BV.
 * Mutate only inside a DB transaction with lockForUpdate().
 */
#[Fillable([
    'member_id', 'left_child_id', 'right_child_id',
    'left_volume', 'right_volume', 'left_volume_carry', 'right_volume_carry',
    'left_lifetime_volume', 'right_lifetime_volume',
])]
class BinaryNode extends Model
{
    /** @use HasFactory<BinaryNodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'left_volume' => 'integer',
            'right_volume' => 'integer',
            'left_volume_carry' => 'integer',
            'right_volume_carry' => 'integer',
            'left_lifetime_volume' => 'integer',
            'right_lifetime_volume' => 'integer',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function leftChild(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'left_child_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function rightChild(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'right_child_id');
    }

    public function childId(PlacementSide $side): ?int
    {
        return $this->{$side->childColumn()};
    }

    public function hasVacancy(PlacementSide $side): bool
    {
        return $this->childId($side) === null;
    }
}
