<?php

namespace App\Http\Requests\Admin;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('manage-members') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize($this->string('phone')->toString()),
            'nid' => (string) preg_replace('/\D+/', '', $this->string('nid')->toString()),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->user_id)],
            'phone' => ['required', 'regex:'.PhoneNumber::PATTERN, $this->notUsedByAnotherActiveMember('phone', $member)],
            'nid' => ['required', 'regex:/^(\d{10}|\d{13}|\d{17})$/', $this->notUsedByAnotherActiveMember('nid', $member)],
            'address' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Same duplicate rule as registration (rule #12), excluding this member.
     */
    private function notUsedByAnotherActiveMember(string $field, Member $member): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $member) {
            $query = Member::query()->where('status', MemberStatus::Active)->whereKeyNot($member->id);

            $taken = $field === 'phone'
                ? $query->whereHas('user', fn ($q) => $q->where('phone', $value))->exists()
                : $query->where('nid', $value)->exists();

            if ($taken) {
                $fail("This {$field} already belongs to another active member.");
            }
        };
    }
}
