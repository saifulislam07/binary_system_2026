<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\Member;
use App\Models\Package;
use App\Models\User;
use App\Services\PlacementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Grows the demo network to a load-test size (default 1,000 extra members,
 * env LOAD_TEST_MEMBERS) through the real engine: every member is activated
 * by PlacementService (BFS spillover) and buys a package, so BV accrues up
 * the tree and referral bonuses are paid. Run on top
 * of the demo data:
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=LoadTestNetworkSeeder
 *   php artisan commission:run
 *
 * Notifications are discarded while seeding. Never run in production.
 */
class LoadTestNetworkSeeder extends DemoNetworkSeeder
{
    private const BATCH = 100;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LoadTestNetworkSeeder must not run in production.');
        }

        // Thousands of demo emails/in-app notices are noise: drop queued jobs.
        config(['queue.connections.seed-discard' => ['driver' => 'null'], 'queue.default' => 'seed-discard']);

        $count = max(1, (int) config('business.load_test_members', 1000));
        $placement = app(PlacementService::class);
        $packages = Package::query()->orderBy('sort_order')->get()->values();
        $sponsors = Member::query()->where('status', MemberStatus::Active)->orderBy('id')->pluck('id')->all();

        if ($sponsors === []) {
            $this->call(DemoNetworkSeeder::class);
            $sponsors = Member::query()->where('status', MemberStatus::Active)->orderBy('id')->pluck('id')->all();
        }

        mt_srand(20260926); // same tree shape on every run
        $startedAt = now()->subDays(30);
        $created = 0;

        while ($created < $count) {
            DB::transaction(function () use (&$created, &$sponsors, $count, $placement, $packages, $startedAt) {
                for ($n = 0; $n < self::BATCH && $created < $count; $n++, $created++) {
                    // Sponsors skew towards earlier members, like a real network.
                    $sponsorId = $sponsors[(int) floor((mt_rand() / mt_getrandmax()) ** 2 * count($sponsors))];
                    $package = $packages[mt_rand(0, $packages->count() - 1)];
                    $activatedAt = $startedAt->addMinutes((int) floor($created * 30 * 24 * 60 / $count));

                    $user = User::factory()->create();
                    $user->assignRole('member');

                    $member = Member::query()->create([
                        'user_id' => $user->id,
                        'sponsor_id' => $sponsorId,
                        'preferred_side' => mt_rand(0, 1) === 0 ? PlacementSide::Left : PlacementSide::Right,
                        'package_id' => $package->id,
                        'nid' => fake()->unique()->numerify('#############'),
                        'phone' => $user->phone,
                        'address' => fake()->address(),
                    ]);

                    $member = $placement->activateMember($member);
                    $member->forceFill(['activated_at' => $activatedAt])->save();
                    $sponsors[] = $member->id;

                    $this->recordPaidSale($member, $package, $activatedAt);
                }
            });

            $this->command->getOutput()->write("\r  {$created}/{$count} members");
        }

        $this->command->getOutput()->writeln('');
    }
}
