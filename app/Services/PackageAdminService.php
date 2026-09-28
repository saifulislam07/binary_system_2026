<?php

namespace App\Services;

use App\Exceptions\ConfigurationException;
use App\Models\Admin;
use App\Models\Package;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits to the package catalog (rule #4: prices and BV are data).
 * Sales keep the amount and BV they were made at, so edits only affect
 * future orders. Packages are never deleted — members and sales point at
 * them — only deactivated, and at least one must stay on sale.
 */
class PackageAdminService
{
    private const AUDITED = ['name', 'description', 'price', 'bv_value', 'cost_of_goods', 'is_qualifying', 'is_active', 'sort_order'];

    /**
     * @param  array{name: string, description: string|null, price: int, bv_value: int, cost_of_goods: int, is_qualifying: bool, is_active: bool, sort_order: int}  $data
     * @param  array<int, int>|null  $products  what's inside: product id => quantity (null = leave as is)
     */
    public function create(array $data, Admin $admin, ?UploadedFile $image = null, ?array $products = null): Package
    {
        return DB::transaction(function () use ($data, $admin, $image, $products) {
            $package = Package::query()->create($data);

            if ($products !== null) {
                $package->products()->sync($this->pivot($products));
            }

            if ($image !== null) {
                $package->addMedia($image)->toMediaCollection('image');
            }

            activity('catalog')
                ->performedOn($package)
                ->causedBy($admin)
                ->withProperties(['attributes' => [...$package->only(self::AUDITED), 'image' => $image !== null ? 'uploaded' : 'none', 'products' => $products ?? []]])
                ->log('Package created');

            return $package;
        });
    }

    /**
     * @param  array{name: string, description: string|null, price: int, bv_value: int, cost_of_goods: int, is_qualifying: bool, is_active: bool, sort_order: int}  $data
     * @param  array<int, int>|null  $products  what's inside: product id => quantity (null = leave as is)
     */
    public function update(Package $package, array $data, Admin $admin, ?UploadedFile $image = null, bool $removeImage = false, ?array $products = null): Package
    {
        return DB::transaction(function () use ($package, $data, $admin, $image, $removeImage, $products) {
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);
            $before = $package->only(self::AUDITED);

            if ($package->is_active && ! $data['is_active']
                && ! Package::query()->where('is_active', true)->whereKeyNot($package->id)->lockForUpdate()->exists()) {
                throw new ConfigurationException('At least one package must stay on sale — activate another package first.');
            }

            $package->fill($data)->save();
            $after = $package->only(self::AUDITED);
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));
            $old = array_intersect_key($before, array_flip($changed));
            $new = array_intersect_key($after, array_flip($changed));

            if ($image !== null) {
                $package->addMedia($image)->toMediaCollection('image'); // single-file: replaces the old one
                $new['image'] = 'replaced';
            } elseif ($removeImage && $package->hasMedia('image')) {
                $package->clearMediaCollection('image');
                $new['image'] = 'removed';
            }

            if ($products !== null) {
                $inside = $package->products()->pluck('package_product.quantity', 'products.id')->map(fn ($q) => (int) $q)->all();
                $package->products()->sync($this->pivot($products));
                $wanted = $products;
                ksort($inside);
                ksort($wanted);

                if ($inside !== $wanted) {
                    $old['products'] = $inside;
                    $new['products'] = $wanted;
                }
            }

            if ($new !== []) {
                activity('catalog')
                    ->performedOn($package)
                    ->causedBy($admin)
                    ->withProperties(['old' => $old, 'attributes' => $new])
                    ->log('Package updated');
            }

            return $package;
        });
    }

    /**
     * @param  array<int, int>  $products
     * @return array<int, array{quantity: int}>
     */
    private function pivot(array $products): array
    {
        return array_map(fn (int $quantity) => ['quantity' => $quantity], $products);
    }
}
