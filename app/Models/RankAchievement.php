<?php

namespace App\Models;

use Database\Factories\RankAchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'rank_id', 'achieved_at'])]
class RankAchievement extends Model
{
    /** @use HasFactory<RankAchievementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'achieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Rank, $this> */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }
}
