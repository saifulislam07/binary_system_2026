<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits to the shop catalog (categories and products). Every change
 * is logged with old/new values. Nothing is deleted — orders point at
 * products — only deactivated.
 */
class CatalogAdminService
{
    private const CATEGORY_FIELDS = ['name', 'name_bn', 'description', 'sort_order', 'is_active'];

    private const PRODUCT_FIELDS = ['category_id', 'name', 'sku', 'brand', 'description', 'highlights', 'price', 'compare_at_price', 'is_active', 'is_featured', 'sort_order'];

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
     * @param  array{category_id: int|null, name: string, sku: string, brand: string|null, description: string|null, highlights: string|null, price: int, compare_at_price: int|null, is_active: bool, is_featured: bool, sort_order: int}  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeMediaIds
     */
    public function saveProduct(?Product $product, array $data, Admin $admin, array $newImages = [], array $removeMediaIds = []): Product
    {
        return DB::transaction(function () use ($product, $data, $admin, $newImages, $removeMediaIds) {
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

            foreach ($newImages as $file) {
                $product->addMedia($file)->toMediaCollection('images');
            }

            if ($newImages !== []) {
                $new['attributes']['images_added'] = count($newImages);
            }

            $this->log($product, $admin, $creating ? 'Product created' : 'Product updated', $new);

            return $product;
        });
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
