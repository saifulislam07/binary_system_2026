<?php

namespace App\Http\Requests\Admin;

use App\Models\BonusRule;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Leadership rules count active team members; sales rules are a taka amount
 * of personal sales. The type is chosen on create and fixed afterwards.
 */
class SaveBonusRuleRequest extends FormRequest
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
        $rule = $this->route('rule');
        $type = $rule instanceof BonusRule ? $rule->type : $this->input('type');

        return [
            'type' => $rule instanceof BonusRule ? ['prohibited'] : ['required', Rule::in([BonusRule::LEADERSHIP, BonusRule::SALES])],
            'name' => ['required', 'string', 'max:100'],
            'threshold' => $type === BonusRule::LEADERSHIP
                ? ['required', 'integer', 'min:1', 'max:1000000']
                : ['required', 'decimal:0,2', 'min:0.01', 'max:100000000'],
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, threshold: int, amount: int, is_active: bool}
     */
    public function ruleData(string $type): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'threshold' => $type === BonusRule::LEADERSHIP
                ? $this->integer('threshold')
                : Money::fromTaka($this->string('threshold')->toString()),
            'amount' => Money::fromTaka($this->string('amount')->toString()),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
