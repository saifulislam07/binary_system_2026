<?php

namespace App\Http\Requests;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KycSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->member() !== null;
    }

    protected function prepareForValidation(): void
    {
        $number = $this->string('document_number')->toString();

        $this->merge([
            'document_number' => $this->input('type') === KycDocumentType::Nid->value
                ? (string) preg_replace('/\D+/', '', $number)
                : strtoupper((string) preg_replace('/\s+/', '', $number)),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KycDocumentType::class)],
            'document_number' => [
                'required', 'string',
                $this->input('type') === KycDocumentType::Passport->value
                    ? 'regex:/^[A-Z0-9]{6,12}$/'
                    : 'regex:/^(\d{10}|\d{13}|\d{17})$/',
            ],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.regex' => $this->input('type') === KycDocumentType::Passport->value
                ? __('Passport number must be 6–12 letters or digits.')
                : __('NID must be 10, 13 or 17 digits.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $open = $this->member()?->kycDocuments()
                    ->whereIn('status', [KycStatus::Pending, KycStatus::Approved])
                    ->exists();

                if ($open) {
                    $validator->errors()->add('type', __('You already have a KYC submission that is under review or approved.'));
                }
            },
        ];
    }

    public function member(): ?Member
    {
        return $this->user('web')?->member;
    }
}
