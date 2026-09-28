<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A shop product (price and compare_at_price in poysha). Products are sold
 * inside packages (package_product); the shop shows which packages carry
 * each one. Photos: media collection `images`, first one is the cover.
 */
#[Fillable(['category_id', 'name', 'sku', 'brand', 'description', 'highlights', 'price', 'compare_at_price', 'is_active', 'is_featured', 'sort_order'])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSlug, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * `card` for grids (4:3), `large` for the product page gallery.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->nonQueued()->fit(Fit::Crop, 800, 600);
        $this->addMediaConversion('large')->nonQueued()->fit(Fit::Max, 1200, 1200);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Package, $this> */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class)->withPivot('quantity');
    }

    public function imageUrl(): ?string
    {
        $media = $this->getFirstMedia('images');

        return $media === null ? null : $media->getUrl('card');
    }

    /**
     * Whole-percent saving against the "was" price, if there is one.
     */
    public function discountPercent(): ?int
    {
        if ($this->compare_at_price === null || $this->compare_at_price <= $this->price) {
            return null;
        }

        return intdiv(($this->compare_at_price - $this->price) * 100, $this->compare_at_price);
    }

    /**
     * @return list<string>
     */
    public function highlightList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->highlights) ?: [])));
    }
}
