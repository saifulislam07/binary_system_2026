<?php

namespace App\Models;

use Database\Factories\RankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Thresholds are poysha (sales) / member count (team). Higher sort_order = higher rank.
 */
#[Fillable(['name', 'slug', 'sort_order', 'min_personal_sales', 'min_team_sales', 'min_active_team', 'bonus_amount'])]
class Rank extends Model
{
    /** @use HasFactory<RankFactory> */
    use HasFactory, HasSlug;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'min_personal_sales' => 'integer',
            'min_team_sales' => 'integer',
            'min_active_team' => 'integer',
            'bonus_amount' => 'integer',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /** @return HasMany<RankAchievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(RankAchievement::class);
    }
}
