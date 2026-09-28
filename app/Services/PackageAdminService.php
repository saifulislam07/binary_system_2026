<?php

namespace App\Services;

use App\Exceptions\ConfigurationException;
use App\Models\Admin;
use App\Models\Package;
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
     */
    public function create(array $data, Admin $admin): Package
    {
        return DB::transaction(function () use ($data, $admin) {
            $package = Package::query()->create($data);

            activity('catalog')
                ->performedOn($package)
                ->causedBy($admin)
                ->withProperties(['attributes' => $package->only(self::AUDITED)])
                ->log('Package created');

            return $package;
        });
    }

    /**
     * @param  array{name: string, description: string|null, price: int, bv_value: int, cost_of_goods: int, is_qualifying: bool, is_active: bool, sort_order: int}  $data
     */
    public function update(Package $package, array $data, Admin $admin): Package
    {
        return DB::transaction(function () use ($package, $data, $admin) {
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);
            $before = $package->only(self::AUDITED);

            if ($package->is_active && ! $data['is_active']
                && ! Package::query()->where('is_active', true)->whereKeyNot($package->id)->lockForUpdate()->exists()) {
                throw new ConfigurationException('At least one package must stay on sale — activate another package first.');
            }

            $package->fill($data)->save();
            $after = $package->only(self::AUDITED);
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                activity('catalog')
                    ->performedOn($package)
                    ->causedBy($admin)
                    ->withProperties([
                        'old' => array_intersect_key($before, array_flip($changed)),
                        'attributes' => array_intersect_key($after, array_flip($changed)),
                    ])
                    ->log('Package updated');
            }

            return $package;
        });
    }
}
