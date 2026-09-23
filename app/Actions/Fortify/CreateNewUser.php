<?php

namespace App\Actions\Fortify;

use App\Actions\Members\RegisterMember;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\Member;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private RegisterMember $registerMember) {}

    /**
     * Validate and create a newly registered user (plus their pending member record).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = [
            ...$input,
            'phone' => PhoneNumber::normalize($input['phone'] ?? null),
            'nid' => preg_replace('/\D+/', '', $input['nid'] ?? '') ?? '',
            'sponsor_code' => strtoupper(trim($input['sponsor_code'] ?? '')),
        ];

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN, $this->notUsedByActiveMember('phone')],
            'nid' => ['required', 'string', 'regex:/^(\d{10}|\d{13}|\d{17})$/', $this->notUsedByActiveMember('nid')],
            'address' => ['required', 'string', 'max:500'],
            'sponsor_code' => ['required', 'string', Rule::exists('members', 'member_code')->where('status', MemberStatus::Active->value)],
            'preferred_side' => ['required', Rule::enum(PlacementSide::class)],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'password' => $this->passwordRules(),
        ], [
            'phone.regex' => __('Enter a valid Bangladeshi mobile number, e.g. 01712345678.'),
            'nid.regex' => __('NID must be 10, 13 or 17 digits.'),
            'sponsor_code.exists' => __('No active member has this sponsor ID.'),
        ])->validate();

        $member = $this->registerMember->handle([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'nid' => $validated['nid'],
            'address' => $validated['address'],
            'sponsor_id' => Member::query()->where('member_code', $validated['sponsor_code'])->value('id'),
            'preferred_side' => PlacementSide::from($validated['preferred_side']),
            'package_id' => (int) $validated['package_id'],
        ], request()->ip(), request()->userAgent());

        return $member->user;
    }

    /**
     * Fraud rule #12: a mobile number or NID may belong to only one active member.
     */
    private function notUsedByActiveMember(string $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field) {
            $query = Member::query()->where('status', MemberStatus::Active);

            $taken = $field === 'phone'
                ? $query->whereHas('user', fn ($q) => $q->where('phone', $value))->exists()
                : $query->where('nid', $value)->exists();

            if ($taken) {
                $fail($field === 'phone'
                    ? __('This mobile number is already registered to an active member.')
                    : __('This NID is already registered to an active member.'));
            }
        };
    }
}
