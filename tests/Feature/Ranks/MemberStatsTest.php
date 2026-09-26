<?php

namespace Tests\Feature\Ranks;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Services\MemberStatsService;
use Database\Seeders\DemoNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The nightly job uses bulk queries; they must agree with the per-member
     * queries the dashboard uses, for every member.
     */
    public function test_bulk_stats_match_per_member_stats()
    {
        $this->seed(DemoNetworkSeeder::class);
        Member::query()->where('member_code', 'MBR-100020')->update(['status' => MemberStatus::Suspended]);

        $service = app(MemberStatsService::class);
        $bulk = $service->forAll();

        $this->assertCount(20, $bulk);

        foreach (Member::query()->whereIn('id', array_keys($bulk))->get() as $member) {
            $this->assertEquals($service->forMember($member), $bulk[$member->id], "Stats differ for {$member->member_code}");
        }

        $root = Member::query()->where('member_code', 'MBR-100001')->firstOrFail();
        $this->assertSame(18, $bulk[$root->id]->activeTeam, '19 downline members, one suspended');
        $this->assertSame(100_000, $bulk[$root->id]->personalSales);
        $this->assertSame(20_400_000, $bulk[$root->id]->teamSales);
    }
}
