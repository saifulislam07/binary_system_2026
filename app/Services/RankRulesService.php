<?php

namespace App\Services;

use App\Exceptions\ConfigurationException;
use App\Models\Admin;
use App\Models\BonusRule;
use App\Models\Rank;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits to rank thresholds/bonuses (rule #11) and leadership/sales
 * bonus rules. Ranks already achieved are never taken away and bonuses
 * already paid stay paid — edits only change who qualifies from now on.
 *
 * Rank names are fixed (Member → … → Diamond): past rank bonuses are
 * reported by name, so renaming would orphan them.
 */
class RankRulesService
{
    private const RANK_FIELDS = ['min_personal_sales', 'min_team_sales', 'min_active_team', 'bonus_amount'];

    private const THRESHOLDS = ['min_personal_sales', 'min_team_sales', 'min_active_team'];

    /**
     * @param  array<int, array{min_personal_sales: int, min_team_sales: int, min_active_team: int, bonus_amount: int}>  $ranks  keyed by rank id
     */
    public function updateRanks(array $ranks, Admin $admin): void
    {
        DB::transaction(function () use ($ranks, $admin) {
            $current = Rank::query()->orderBy('sort_order')->lockForUpdate()->get();
            $previous = null;

            // Every threshold must be at least the one below it, or a member
            // could qualify for Gold without Silver and "skip" a rank bonus.
            foreach ($current as $rank) {
                $new = $ranks[$rank->id] ?? throw new ConfigurationException("Missing values for rank {$rank->name}.");

                if ($previous !== null) {
                    foreach (self::THRESHOLDS as $field) {
                        if ($new[$field] < $previous[1][$field]) {
                            throw new ConfigurationException("{$rank->name} needs at least as much as {$previous[0]} on every requirement ({$this->label($field)}).");
                        }
                    }
                }

                $previous = [$rank->name, $new];
            }

            $old = [];
            $changed = [];

            foreach ($current as $rank) {
                $before = $rank->only(self::RANK_FIELDS);
                $rank->fill(array_intersect_key($ranks[$rank->id], array_flip(self::RANK_FIELDS)))->save();
                $diff = array_diff_assoc(array_map('strval', $rank->only(self::RANK_FIELDS)), array_map('strval', $before));

                if ($diff !== []) {
                    $old[$rank->name] = array_intersect_key($before, $diff);
                    $changed[$rank->name] = array_intersect_key($rank->only(self::RANK_FIELDS), $diff);
                }
            }

            if ($changed !== []) {
                activity('ranks')
                    ->causedBy($admin)
                    ->withProperties(['old' => $old, 'attributes' => $changed])
                    ->log('Rank thresholds updated');
            }
        });
    }

    /**
     * @param  array{type: string, name: string, threshold: int, amount: int, is_active: bool}  $data
     */
    public function createBonusRule(array $data, Admin $admin): BonusRule
    {
        return DB::transaction(function () use ($data, $admin) {
            $rule = BonusRule::query()->create($data);

            activity('ranks')
                ->performedOn($rule)
                ->causedBy($admin)
                ->withProperties(['attributes' => $rule->only(['type', 'name', 'threshold', 'amount', 'is_active'])])
                ->log('Bonus rule created');

            return $rule;
        });
    }

    /**
     * The type stays fixed: members already paid under a rule were paid
     * for that kind of achievement.
     *
     * @param  array{name: string, threshold: int, amount: int, is_active: bool}  $data
     */
    public function updateBonusRule(BonusRule $rule, array $data, Admin $admin): BonusRule
    {
        return DB::transaction(function () use ($rule, $data, $admin) {
            $rule = BonusRule::query()->lockForUpdate()->findOrFail($rule->id);
            $fields = ['name', 'threshold', 'amount', 'is_active'];
            $before = $rule->only($fields);

            $rule->fill($data)->save();
            $diff = array_diff_assoc(array_map('strval', $rule->only($fields)), array_map('strval', $before));

            if ($diff !== []) {
                activity('ranks')
                    ->performedOn($rule)
                    ->causedBy($admin)
                    ->withProperties([
                        'old' => array_intersect_key($before, $diff),
                        'attributes' => array_intersect_key($rule->only($fields), $diff),
                    ])
                    ->log('Bonus rule updated');
            }

            return $rule;
        });
    }

    private function label(string $field): string
    {
        return match ($field) {
            'min_personal_sales' => 'personal sales',
            'min_team_sales' => 'team sales',
            default => 'active team',
        };
    }
}
