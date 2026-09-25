<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use App\Enums\WithdrawalMethodType;
use App\Models\Member;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class WithdrawalRequest extends FormRequest
{
    public const MOBILE_PROVIDERS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'upay' => 'Upay'];

    public function authorize(): bool
    {
        return $this->member()?->status === MemberStatus::Active;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile_number')) {
            $this->merge(['mobile_number' => PhoneNumber::normalize($this->string('mobile_number')->toString())]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $newMethod = fn () => ! $this->filled('withdrawal_method_id');
        $type = $this->input('method');

        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'withdrawal_method_id' => [
                'nullable', 'integer',
                Rule::exists('withdrawal_methods', 'id')->where('member_id', $this->member()?->id),
            ],
            'method' => [Rule::requiredIf($newMethod), 'nullable', Rule::enum(WithdrawalMethodType::class)],

            'bank_name' => [Rule::requiredIf($newMethod() && $type === 'bank'), 'nullable', 'string', 'max:100'],
            'branch_name' => [Rule::requiredIf($newMethod() && $type === 'bank'), 'nullable', 'string', 'max:100'],
            'account_name' => [Rule::requiredIf($newMethod() && $type === 'bank'), 'nullable', 'string', 'max:100'],
            'account_number' => [Rule::requiredIf($newMethod() && $type === 'bank'), 'nullable', 'regex:/^\d{6,20}$/'],
            'routing_number' => ['nullable', 'regex:/^\d{9}$/'],

            'provider' => [Rule::requiredIf($newMethod() && $type === 'mobile_banking'), 'nullable', Rule::in(array_keys(self::MOBILE_PROVIDERS))],
            'mobile_number' => [Rule::requiredIf($newMethod() && $type === 'mobile_banking'), 'nullable', 'regex:'.PhoneNumber::PATTERN],

            'save_method' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => __('Enter an amount in taka, e.g. 1500 or 1500.50.'),
            'mobile_number.regex' => __('Enter a valid Bangladeshi mobile number.'),
            'account_number.regex' => __('Account number must be 6–20 digits.'),
            'routing_number.regex' => __('Routing number must be 9 digits.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('amount') || $this->member() === null) {
                    return;
                }

                $amount = $this->amountInPoysha();
                $minimum = app(WithdrawalService::class)->minimumAmount();
                $balance = app(WalletService::class)->balance($this->member());

                if ($amount < $minimum) {
                    $validator->errors()->add('amount', __('The minimum withdrawal is :min.', ['min' => Money::format($minimum)]));
                } elseif ($amount > $balance) {
                    $validator->errors()->add('amount', __('You can withdraw at most :max.', ['max' => Money::format(max(0, $balance))]));
                }
            },
        ];
    }

    public function member(): ?Member
    {
        return $this->user('web')?->member;
    }

    public function amountInPoysha(): int
    {
        try {
            return Money::fromTaka($this->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    /**
     * Account details for a new method, in the shape stored on
     * withdrawal_methods.details / withdrawals.account_details.
     *
     * @return array<string, string>
     */
    public function newMethodDetails(): array
    {
        return $this->input('method') === WithdrawalMethodType::Bank->value
            ? array_filter([
                'bank_name' => $this->string('bank_name')->trim()->toString(),
                'branch_name' => $this->string('branch_name')->trim()->toString(),
                'account_name' => $this->string('account_name')->trim()->toString(),
                'account_number' => $this->string('account_number')->toString(),
                'routing_number' => $this->string('routing_number')->toString(),
            ])
            : [
                'provider' => $this->string('provider')->toString(),
                'mobile_number' => $this->string('mobile_number')->toString(),
            ];
    }
}
