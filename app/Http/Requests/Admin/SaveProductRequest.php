<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Support\Money;
use App\Support\RichText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Prices are entered in taka ("1250.50") and stored as poysha. The
 * description comes from the rich-text editor and is sanitized here; key
 * features arrive as a list and are stored one per line. A brand is picked
 * from the list, or typed as `new_brand` to create it.
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
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'new_brand' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($id)],
            'description' => ['nullable', 'string', 'max:20000'],
            'highlights' => ['nullable', 'array', 'max:20'],
            'highlights.*' => ['nullable', 'string', 'max:200'],
            'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'compare_at_price' => ['nullable', 'decimal:0,2', 'gt:price', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=400,min_height=300'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'image_order' => ['nullable', 'array', 'max:50'],
            'image_order.*' => ['string', 'regex:/^[mn]:\d+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'compare_at_price.gt' => 'The "was" price must be higher than the price.',
            'highlights.*.max' => 'Each key feature can be at most 200 characters.',
        ];
    }

    /**
     * @return array{category_id: int|null, brand_id: int|null, name: string, sku: string, description: string|null, highlights: string|null, price: int, compare_at_price: int|null, is_active: bool, is_featured: bool, sort_order: int}
     */
    public function productData(): array
    {
        return [
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'brand_id' => $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'name' => $this->string('name')->trim()->toString(),
            'sku' => $this->string('sku')->trim()->upper()->toString(),
            'description' => RichText::clean($this->filled('description') ? $this->string('description')->toString() : null),
            'highlights' => $this->highlightsText(),
            'price' => Money::fromTaka($this->string('price')->toString()),
            'compare_at_price' => $this->filled('compare_at_price') ? Money::fromTaka($this->string('compare_at_price')->toString()) : null,
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    public function newBrandName(): ?string
    {
        return $this->filled('new_brand') ? $this->string('new_brand')->trim()->toString() : null;
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

    /**
     * @return list<string>|null
     */
    public function imageOrder(): ?array
    {
        return $this->has('image_order') ? array_values(array_map('strval', (array) $this->input('image_order'))) : null;
    }

    private function highlightsText(): ?string
    {
        $features = array_filter(
            array_map(fn ($line) => trim(preg_replace('/\s+/u', ' ', (string) $line) ?? ''), (array) $this->input('highlights', [])),
            fn (string $line) => $line !== '',
        );

        return $features === [] ? null : implode("\n", $features);
    }
}
