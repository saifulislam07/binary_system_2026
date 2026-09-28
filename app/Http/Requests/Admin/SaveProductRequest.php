<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Prices are entered in taka ("1250.50") and stored as poysha.
 */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-catalog on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $id = $product instanceof Product ? $product->id : null;

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($id)],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'highlights' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'compare_at_price' => ['nullable', 'decimal:0,2', 'gt:price', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=400,min_height=300'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['compare_at_price.gt' => 'The "was" price must be higher than the price.'];
    }

    /**
     * @return array{category_id: int|null, name: string, sku: string, brand: string|null, description: string|null, highlights: string|null, price: int, compare_at_price: int|null, is_active: bool, is_featured: bool, sort_order: int}
     */
    public function productData(): array
    {
        $text = fn (string $key) => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'name' => $this->string('name')->trim()->toString(),
            'sku' => $this->string('sku')->trim()->upper()->toString(),
            'brand' => $text('brand'),
            'description' => $text('description'),
            'highlights' => $text('highlights'),
            'price' => Money::fromTaka($this->string('price')->toString()),
            'compare_at_price' => $this->filled('compare_at_price') ? Money::fromTaka($this->string('compare_at_price')->toString()) : null,
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function newImages(): array
    {
        return array_values(array_filter((array) $this->file('images', []), fn ($f) => $f instanceof UploadedFile));
    }

    /**
     * @return list<int>
     */
    public function removeImageIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('remove_images', [])));
    }
}
