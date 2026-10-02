<?php

namespace App\Http\Requests\Admin;

use App\Support\RichText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A membership page section: title and rich-text body in English (required)
 * and Bangla (optional; the page falls back to English).
 */
class SaveMembershipSectionRequest extends FormRequest
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
        return [
            'title_en' => ['required', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'body_en' => ['required', 'string', 'max:20000'],
            'body_bn' => ['nullable', 'string', 'max:20000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['body_en.required' => 'Write the English text; the Bangla page falls back to it.'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('body_en') && RichText::clean($this->string('body_en')->toString()) === null) {
                $validator->errors()->add('body_en', 'The English text is empty.');
            }
        });
    }

    /**
     * @return array{title_en: string, title_bn: string|null, body_en: string, body_bn: string|null, sort_order: int, is_active: bool}
     */
    public function sectionData(): array
    {
        return [
            'title_en' => $this->string('title_en')->trim()->toString(),
            'title_bn' => $this->filled('title_bn') ? $this->string('title_bn')->trim()->toString() : null,
            'body_en' => (string) RichText::clean($this->string('body_en')->toString()),
            'body_bn' => RichText::clean($this->filled('body_bn') ? $this->string('body_bn')->toString() : null),
            'sort_order' => $this->integer('sort_order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
