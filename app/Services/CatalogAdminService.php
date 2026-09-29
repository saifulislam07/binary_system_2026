<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Admin edits to the shop catalog (categories, brands and products). Every change
 * is logged with old/new values. Nothing is deleted — orders point at
 * products — only deactivated.
 */
class CatalogAdminService
{
    private const CATEGORY_FIELDS = ['name', 'name_bn', 'description', 'sort_order', 'is_active'];

    private const BRAND_FIELDS = ['name', 'description', 'sort_order', 'is_active'];

    private const PRODUCT_FIELDS = ['category_id', 'brand_id', 'name', 'sku', 'description', 'highlights', 'price', 'compare_at_price', 'is_active', 'is_featured', 'sort_order'];

    /**
     * @param  array{name: string, name_bn: string|null, description: string|null, sort_order: int, is_active: bool}  $data
     */
    public function saveCategory(?Category $category, array $data, Admin $admin, ?UploadedFile $image = null, bool $removeImage = false): Category
    {
        return DB::transaction(function () use ($category, $data, $admin, $image, $removeImage) {
            $category ??= new Category;
            $creating = ! $category->exists;
            $before = $creating ? [] : $category->only(self::CATEGORY_FIELDS);

            $category->fill($data)->save();
            $new = $this->diff($before, $category->only(self::CATEGORY_FIELDS));

            if ($image !== null) {
                $category->addMedia($image)->toMediaCollection('image');
                $new['attributes']['image'] = 'replaced';
            } elseif ($removeImage && $category->hasMedia('image')) {
                $category->clearMediaCollection('image');
                $new['attributes']['image'] = 'removed';
            }

            $this->log($category, $admin, $creating ? 'Category created' : 'Category updated', $new);

            return $category;
        });
    }

    /**
     * @param  array{name: string, description: string|null, sort_order: int, is_active: bool}  $data
     */
    public function saveBrand(?Brand $brand, array $data, Admin $admin, ?UploadedFile $logo = null, bool $removeLogo = false): Brand
    {
        return DB::transaction(function () use ($brand, $data, $admin, $logo, $removeLogo) {
            $brand ??= new Brand;
            $creating = ! $brand->exists;
            $before = $creating ? [] : $brand->only(self::BRAND_FIELDS);

            $brand->fill($data)->save();
            $new = $this->diff($before, $brand->only(self::BRAND_FIELDS));

            if ($logo !== null) {
                $brand->addMedia($logo)->toMediaCollection('logo');
                $new['attributes']['logo'] = 'replaced';
            } elseif ($removeLogo && $brand->hasMedia('logo')) {
                $brand->clearMediaCollection('logo');
                $new['attributes']['logo'] = 'removed';
            }

            $this->log($brand, $admin, $creating ? 'Brand created' : 'Brand updated', $new);

            return $brand;
        });
    }

    /**
     * The brand with this name (any letter case), created on the spot from
     * the product form when it doesn't exist yet.
     */
    public function brandNamed(string $name, Admin $admin): Brand
    {
        $name = trim($name);

        return Brand::query()->where('name', $name)->first()
            ?? $this->saveBrand(null, [
                'name' => $name,
                'description' => null,
                'sort_order' => (int) Brand::query()->max('sort_order') + 1,
                'is_active' => true,
            ], $admin);
    }

    /**
     * @param  array{category_id: int|null, brand_id: int|null, name: string, sku: string, description: string|null, highlights: string|null, price: int, compare_at_price: int|null, is_active: bool, is_featured: bool, sort_order: int}  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeMediaIds
     * @param  list<string>|null  $imageOrder  photo order, cover first: "m:{media id}" for saved photos, "n:{index}" for $newImages
     */
    public function saveProduct(?Product $product, array $data, Admin $admin, array $newImages = [], array $removeMediaIds = [], ?array $imageOrder = null): Product
    {
        return DB::transaction(function () use ($product, $data, $admin, $newImages, $removeMediaIds, $imageOrder) {
            $product ??= new Product;
            $creating = ! $product->exists;
            $before = $creating ? [] : $product->only(self::PRODUCT_FIELDS);
            $product->fill($data)->save();
            $new = $this->diff($before, $product->only(self::PRODUCT_FIELDS));

            if ($removeMediaIds !== []) {
                $removed = $product->getMedia('images')->whereIn('id', $removeMediaIds);
                $removed->each->delete();
                $new['attributes']['images_removed'] = $removed->count();
            }

            $added = [];

            foreach ($newImages as $file) {
                $added[] = $product->addMedia($file)->toMediaCollection('images')->id;
            }

            if ($newImages !== []) {
                $new['attributes']['images_added'] = count($newImages);
            }

            if ($imageOrder !== null) {
                $this->orderImages($product, $imageOrder, $added, $new);
            }

            $this->log($product, $admin, $creating ? 'Product created' : 'Product updated', $new);

            return $product;
        });
    }

    /**
     * Applies the admin's photo order. Unknown tokens are ignored and any
     * photo the order leaves out keeps its place after the listed ones.
     *
     * @param  list<string>  $order
     * @param  list<int>  $added  media ids of this save's uploads, in upload order
     * @param  array{old: array<string, mixed>, attributes: array<string, mixed>}  $changes
     */
    private function orderImages(Product $product, array $order, array $added, array &$changes): void
    {
        $current = $product->load('media')->getMedia('images')->pluck('id')->all();
        $ids = [];

        foreach ($order as $token) {
            [$kind, $ref] = array_pad(explode(':', $token, 2), 2, '');
            $id = match ($kind) {
                'm' => in_array((int) $ref, $current, true) ? (int) $ref : null,
                'n' => $added[(int) $ref] ?? null,
                default => null,
            };

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $ids = [...$ids, ...array_values(array_diff($current, $ids))];

        if ($ids === $current) {
            return;
        }

        Media::setNewOrder($ids);
        $product->load('media');
        $changes['attributes']['images_reordered'] = true;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string, mixed>, attributes: array<string, mixed>}
     */
    private function diff(array $before, array $after): array
    {
        $normal = fn (mixed $v) => is_bool($v) ? (int) $v : ($v === null ? null : (string) $v);
        $changed = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $normal($before[$key]) !== $normal($value)) {
                $changed[] = $key;
            }
        }

        return [
            'old' => array_intersect_key($before, array_flip($changed)),
            'attributes' => array_intersect_key($after, array_flip($changed)),
        ];
    }

    /**
     * @param  array{old: array<string, mixed>, attributes: array<string, mixed>}  $changes
     */
    private function log(Model $subject, Admin $admin, string $description, array $changes): void
    {
        if ($changes['attributes'] === [] && $changes['old'] === []) {
            return;
        }

        activity('catalog')->performedOn($subject)->causedBy($admin)->withProperties($changes)->log($description);
    }
}
