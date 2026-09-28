<?php

namespace App\Http\Requests\Admin;

use App\Models\Rank;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One form for all ranks: sales thresholds and bonuses in taka, active team
 * as a head count.
 */
class UpdateRanksRequest extends FormRequest
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
        $rules = ['ranks' => ['required', 'array']];

        foreach (Rank::query()->pluck('id') as $id) {
            $rules["ranks.{$id}.min_personal_sales"] = ['required', 'decimal:0,2', 'min:0', 'max:100000000'];
            $rules["ranks.{$id}.min_team_sales"] = ['required', 'decimal:0,2', 'min:0', 'max:100000000'];
            $rules["ranks.{$id}.min_active_team"] = ['required', 'integer', 'min:0', 'max:1000000'];
            $rules["ranks.{$id}.bonus_amount"] = ['required', 'decimal:0,2', 'min:0', 'max:10000000'];
        }

        return $rules;
    }

    /**
     * @return array<int, array{min_personal_sales: int, min_team_sales: int, min_active_team: int, bonus_amount: int}>
     */
    public function ranks(): array
    {
        $ranks = [];

        foreach ((array) $this->validated('ranks') as $id => $values) {
            $ranks[(int) $id] = [
                'min_personal_sales' => Money::fromTaka((string) $values['min_personal_sales']),
                'min_team_sales' => Money::fromTaka((string) $values['min_team_sales']),
                'min_active_team' => (int) $values['min_active_team'],
                'bonus_amount' => Money::fromTaka((string) $values['bonus_amount']),
            ];
        }

        return $ranks;
    }
}
