<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category instanceof Category ? $category->id : null)],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=225'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, name_bn: string|null, description: string|null, sort_order: int, is_active: bool}
     */
    public function categoryData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'name_bn' => $this->filled('name_bn') ? $this->string('name_bn')->trim()->toString() : null,
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'sort_order' => $this->integer('sort_order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
