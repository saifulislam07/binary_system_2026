<?php

namespace Tests\Feature\Fraud;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Rule #12 under a race: two pending sign-ups with the same NID (allowed —
 * pending never blocks) pay at the same moment. Two OS processes activate
 * them concurrently against the real MySQL test database; the unique index
 * on members.active_nid must let exactly one through.
 */
class DuplicateActivationRaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_duplicate_nid_is_rejected_even_under_a_race()
    {
        $root = app(PlacementService::class)->activateMember(Member::factory()->create());
        $twins = Member::factory()->count(2)->create(['nid' => '9876543210', 'sponsor_id' => $root->id]);

        $processes = $twins->map(function (Member $member) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/activate-members.php'), (string) $member->id],
                base_path(),
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => config('database.default'),
                    'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
                    'CACHE_STORE' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                ],
                timeout: 120,
            );
            $process->start();

            return $process;
        });

        $outcomes = $processes->map(function (Process $process) {
            $process->wait();

            return [
                'ok' => $process->isSuccessful(),
                'duplicate' => str_contains($process->getErrorOutput().$process->getOutput(), 'DuplicateMemberException'),
            ];
        });

        $this->assertSame(1, $outcomes->where('ok', true)->count(), 'Exactly one activation succeeds');
        $this->assertSame(1, $outcomes->where('duplicate', true)->count(), 'The other is refused as a duplicate');

        $this->assertSame(1, Member::query()->where('nid', '9876543210')->where('status', MemberStatus::Active)->count());
        $this->assertSame(1, Member::query()->where('nid', '9876543210')->where('status', MemberStatus::Pending)->whereNull('member_code')->count());
    }
}
