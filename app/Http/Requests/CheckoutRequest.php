<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->user('web')?->member;

        return $member !== null && $member->status !== MemberStatus::Suspended;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(PaymentGatewayManager $gateways): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'gateway' => ['required', Rule::in(array_map(fn (PaymentGateway $g) => $g->value, $gateways->available()))],
        ];
    }
}
