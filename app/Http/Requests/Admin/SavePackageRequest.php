<?php

namespace App\Http\Requests\Admin;

use App\Models\Package;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Taka and BV are entered as decimals ("1250.50") and stored ×100 as integers.
 */
class SavePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-settings on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('packages', 'name')->ignore($package instanceof Package ? $package->id : null)],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'bv_value' => ['required', 'decimal:0,2', 'min:0', 'max:10000000'],
            'cost_of_goods' => ['required', 'decimal:0,2', 'min:0', 'max:10000000'],
            'is_qualifying' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            // Product photo for the shop; cropped to a 4:3 card.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=400,min_height=300'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, price: int, bv_value: int, cost_of_goods: int, is_qualifying: bool, is_active: bool, sort_order: int}
     */
    public function packageData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'price' => Money::fromTaka($this->string('price')->toString()),
            'bv_value' => Money::fromTaka($this->string('bv_value')->toString()),
            'cost_of_goods' => Money::fromTaka($this->string('cost_of_goods')->toString()),
            'is_qualifying' => $this->boolean('is_qualifying'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }
}
