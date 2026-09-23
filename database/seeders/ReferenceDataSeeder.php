<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\CommissionRule;
use App\Models\Package;
use App\Models\Rank;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Defaults every environment needs, production included. Idempotent: it only
 * creates missing rows and never overwrites values an admin has changed.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $this->seedPackages();
        $this->seedCommissionRules();
        $this->seedRanks();
        $this->seedSettings();
        $this->seedAdmin();
    }

    private function seedPackages(): void
    {
        // price & cost_of_goods in poysha; bv_value in centi-BV (1 BV per ৳1).
        $packages = [
            ['name' => 'Basic', 'price' => 100_000, 'sort_order' => 1],
            ['name' => 'Standard', 'price' => 500_000, 'sort_order' => 2],
            ['name' => 'Premium', 'price' => 1_000_000, 'sort_order' => 3],
            ['name' => 'Business', 'price' => 2_500_000, 'sort_order' => 4],
        ];

        foreach ($packages as $package) {
            Package::query()->firstOrCreate(['name' => $package['name']], [
                'description' => "{$package['name']} package",
                'price' => $package['price'],
                'bv_value' => $package['price'],
                'cost_of_goods' => intdiv($package['price'] * 40, 100),
                'is_qualifying' => true,
                'is_active' => true,
                'sort_order' => $package['sort_order'],
            ]);
        }
    }

    private function seedCommissionRules(): void
    {
        $rules = [
            CommissionRule::BINARY_RATE_BPS => ['1000', 'Binary commission rate in basis points (1000 = 10%) of matched BV'],
            CommissionRule::REFERRAL_RATE_BPS => ['500', 'Referral bonus rate in basis points (500 = 5%) of qualifying sale amount'],
            CommissionRule::DAILY_CAP => ['500000', 'Max binary commission per member per day, poysha (৳5,000)'],
            CommissionRule::WEEKLY_CAP => ['2000000', 'Max binary commission per member per week, poysha (৳20,000)'],
            CommissionRule::MONTHLY_CAP => ['5000000', 'Max binary commission per member per month, poysha (৳50,000)'],
            CommissionRule::CARRY_FORWARD_ENABLED => ['1', 'Carry unmatched volume to the next cycle (1) or discard it (0)'],
            CommissionRule::CAP_OVERFLOW_BEHAVIOR => ['void', "Commission above a cap: 'void' or 'carry_forward'"],
        ];

        foreach ($rules as $key => [$value, $description]) {
            CommissionRule::query()->firstOrCreate(['key' => $key], compact('value', 'description'));
        }
    }

    private function seedRanks(): void
    {
        // [name, min_personal_sales, min_team_sales, min_active_team, bonus_amount] — money in poysha.
        $ranks = [
            ['Member', 0, 0, 0, 0],
            ['Bronze', 500_000, 5_000_000, 2, 100_000],
            ['Silver', 1_000_000, 20_000_000, 5, 300_000],
            ['Gold', 2_500_000, 50_000_000, 10, 1_000_000],
            ['Platinum', 2_500_000, 150_000_000, 25, 2_500_000],
            ['Diamond', 5_000_000, 500_000_000, 50, 10_000_000],
        ];

        foreach ($ranks as $order => [$name, $personal, $team, $active, $bonus]) {
            Rank::query()->firstOrCreate(['name' => $name], [
                'sort_order' => $order,
                'min_personal_sales' => $personal,
                'min_team_sales' => $team,
                'min_active_team' => $active,
                'bonus_amount' => $bonus,
            ]);
        }
    }

    private function seedSettings(): void
    {
        Setting::query()->firstOrCreate(['key' => Setting::MIN_WITHDRAWAL], [
            'value' => '100000',
            'description' => 'Minimum withdrawal amount, poysha (৳1,000)',
        ]);
    }

    private function seedAdmin(): void
    {
        $admin = Admin::query()->firstOrCreate(
            ['email' => config('business.seed_admin.email')],
            ['name' => 'Super Admin', 'password' => config('business.seed_admin.password')],
        );

        $admin->assignRole('admin');
    }
}
