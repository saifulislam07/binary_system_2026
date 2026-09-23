<?php

namespace Tests\Feature\Members;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\BinaryNode;
use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Activates members from several OS processes at once against the real
 * MySQL test database, so row locks are actually contended. Data must be
 * committed for the child processes to see it, so this class opts out of
 * the per-test transaction and forces a fresh migration afterwards.
 */
class MemberActivationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const PROCESSES = 4;

    private const MEMBERS_PER_PROCESS = 5;

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function tearDown(): void
    {
        // This test committed rows; make the next test class start from a clean database.
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_parallel_activations_get_unique_sequential_codes_and_distinct_slots()
    {
        $placement = app(PlacementService::class);
        $root = $placement->activateMember(Member::factory()->create());

        $total = self::PROCESSES * self::MEMBERS_PER_PROCESS;

        // Everyone wants the same spot (root's left leg), so they all race for the same slots.
        $pending = Member::factory()->count($total)->create([
            'sponsor_id' => $root->id,
            'preferred_side' => PlacementSide::Left,
        ])->pluck('id');

        $processes = $pending->chunk(self::MEMBERS_PER_PROCESS)->map(function ($ids) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/activate-members.php'), $ids->implode(',')],
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

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Child failed: '.$process->getErrorOutput().$process->getOutput());
        }

        // Codes: unique and a contiguous run straight after the root's code.
        $this->assertSame($total, Member::query()->whereKey($pending)->whereNotNull('member_code')->count(), 'Some members were not activated.');

        $codes = Member::query()->whereKey($pending)->pluck('member_code')
            ->map(fn (string $code) => (int) substr($code, 4))
            ->sort()
            ->values();
        $rootNumber = (int) substr((string) $root->member_code, 4);

        $this->assertCount($total, $codes->unique());
        $this->assertSame(range($rootNumber + 1, $rootNumber + $total), $codes->all());

        // Tree: everyone active and placed, and both representations agree.
        $members = Member::query()->with('binaryNode')->get()->keyBy('id');
        $this->assertTrue($members->every(fn (Member $m) => $m->status === MemberStatus::Active));
        $this->assertSame($total + 1, BinaryNode::query()->count());

        foreach ($members as $member) {
            if ($member->id === $root->id) {
                continue;
            }

            $parentNode = $members[$member->placement_parent_id]->binaryNode;
            $this->assertNotNull($member->placement_side);
            $this->assertSame($member->id, $parentNode->childId($member->placement_side));
        }

        // Every member sits in the root's left leg.
        $this->assertNull($root->binaryNode()->first()?->right_child_id);
    }
}
