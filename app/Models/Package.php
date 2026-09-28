<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A purchasable package. price/cost_of_goods are poysha, bv_value is centi-BV.
 * One product photo (media collection `image`, public disk) for the shop.
 */
#[Fillable(['name', 'slug', 'description', 'price', 'bv_value', 'cost_of_goods', 'is_qualifying', 'is_active', 'sort_order'])]
class Package extends Model implements HasMedia
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, HasSlug, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * The shop card crop: 4:3, made at upload time so it's there immediately.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->nonQueued()
            ->fit(Fit::Crop, 800, 600);
    }

    public function imageUrl(): ?string
    {
        $media = $this->getFirstMedia('image');

        return $media === null ? null : $media->getUrl('card');
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'bv_value' => 'integer',
            'cost_of_goods' => 'integer',
            'is_qualifying' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
