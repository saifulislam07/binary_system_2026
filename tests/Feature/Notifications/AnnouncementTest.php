<?php

namespace Tests\Feature\Notifications;

use App\Enums\MemberStatus;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\Package;
use App\Models\Rank;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ImportantAnnouncement;
use App\Services\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = Admin::factory()->superAdmin()->create();
    }

    private function rank(string $name): Rank
    {
        return Rank::query()->where('name', $name)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function send(array $segment = [], array $channels = ['mail']): Announcement
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.announcements.store'), [
                'title' => 'Eid holiday · ঈদের ছুটি',
                'body' => 'Withdrawals are paid after the holiday.',
                'audience' => 'active',
                'channels' => $channels,
                ...$segment,
            ])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        return Announcement::query()->latest('id')->firstOrFail();
    }

    public function test_the_broadcast_reaches_the_selected_segment_only()
    {
        $active = Member::factory()->active()->count(3)->create();
        $pending = Member::factory()->create();
        $suspended = Member::factory()->active()->create();
        $suspended->forceFill(['status' => MemberStatus::Suspended])->save();

        $announcement = $this->send(['audience' => 'active']);

        foreach ($active as $member) {
            Notification::assertSentTo($member->user, ImportantAnnouncement::class, fn (ImportantAnnouncement $n) => $n->announcement->is($announcement));
        }
        Notification::assertNotSentTo($pending->user, ImportantAnnouncement::class);
        Notification::assertNotSentTo($suspended->user, ImportantAnnouncement::class);

        $announcement->refresh();
        $this->assertSame(3, $announcement->recipients);
        $this->assertNotNull($announcement->sent_at);
    }

    public function test_rank_segment_means_that_rank_and_above()
    {
        $silver = Member::factory()->active()->create(['current_rank_id' => $this->rank('Silver')->id]);
        $gold = Member::factory()->active()->create(['current_rank_id' => $this->rank('Gold')->id]);
        $diamond = Member::factory()->active()->create(['current_rank_id' => $this->rank('Diamond')->id]);
        $unranked = Member::factory()->active()->create(['current_rank_id' => null]);

        $this->send(['min_rank_id' => $this->rank('Gold')->id]);

        Notification::assertSentTo($gold->user, ImportantAnnouncement::class);
        Notification::assertSentTo($diamond->user, ImportantAnnouncement::class);
        Notification::assertNotSentTo($silver->user, ImportantAnnouncement::class);
        Notification::assertNotSentTo($unranked->user, ImportantAnnouncement::class);
    }

    public function test_the_lowest_rank_includes_members_never_promoted()
    {
        $unranked = Member::factory()->active()->create(['current_rank_id' => null]);
        $bronze = Member::factory()->active()->create(['current_rank_id' => $this->rank('Bronze')->id]);

        $announcement = $this->send(['min_rank_id' => $this->rank('Member')->id]);

        Notification::assertSentTo($unranked->user, ImportantAnnouncement::class);
        Notification::assertSentTo($bronze->user, ImportantAnnouncement::class);
        $this->assertSame(2, $announcement->fresh()?->recipients);
    }

    public function test_package_segment_and_everyone()
    {
        $premium = Package::query()->where('name', 'Premium')->firstOrFail();
        $onPremium = Member::factory()->active()->create(['package_id' => $premium->id]);
        $pendingPremium = Member::factory()->create(['package_id' => $premium->id]);
        $onBasic = Member::factory()->active()->create(['package_id' => Package::query()->where('name', 'Basic')->firstOrFail()->id]);

        $this->send(['package_id' => $premium->id, 'audience' => 'all']);

        Notification::assertSentTo($onPremium->user, ImportantAnnouncement::class);
        Notification::assertSentTo($pendingPremium->user, ImportantAnnouncement::class);
        Notification::assertNotSentTo($onBasic->user, ImportantAnnouncement::class);
    }

    public function test_channels_are_the_admins_choice_within_what_is_configured()
    {
        $member = Member::factory()->active()->create();

        $inAppOnly = $this->send(channels: []);
        $this->assertSame(['database'], (new ImportantAnnouncement($inAppOnly))->via($member->user));

        $withSms = $this->send(channels: ['mail', 'sms']);
        $this->assertSame(['database', EmailChannel::class], (new ImportantAnnouncement($withSms))->via($member->user), 'SMS is off in config');

        config(['notifications.channels.sms' => true]);
        $this->assertSame(['database', EmailChannel::class, SmsChannel::class], (new ImportantAnnouncement($withSms))->via($member->user));
    }

    public function test_sending_is_audited_and_not_repeated()
    {
        Member::factory()->active()->count(2)->create();

        $announcement = $this->send();

        $log = Activity::query()->where('log_name', 'announcements')->firstOrFail();
        $this->assertSame('Announcement sent', $log->description);
        $this->assertTrue($log->causer?->is($this->admin));
        $this->assertSame(2, $log->properties['recipients']);

        $this->assertSame(0, app(AnnouncementService::class)->deliver($announcement), 'Already delivered');
    }

    public function test_the_compose_form_previews_the_recipient_count()
    {
        Member::factory()->active()->count(2)->create();
        Member::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.announcements.preview', ['audience' => 'active']))
            ->assertOk()
            ->assertExactJson(['recipients' => 2]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.announcements.preview', ['audience' => 'all']))
            ->assertExactJson(['recipients' => 3]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('New important announcement');
    }

    public function test_validation()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.announcements.store'), ['audience' => 'everybody', 'channels' => ['fax']])
            ->assertSessionHasErrors(['title', 'body', 'audience', 'channels.0']);

        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_only_admins_with_send_announcements_can_broadcast()
    {
        $support = Admin::factory()->create()->assignRole('support');
        $finance = Admin::factory()->create()->assignRole('finance');

        foreach ([$support, $finance] as $admin) {
            $this->actingAs($admin, 'admin')->get(route('admin.announcements.index'))->assertForbidden();
            $this->actingAs($admin, 'admin')
                ->post(route('admin.announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'active'])
                ->assertForbidden();
        }

        $this->assertSame(0, Announcement::query()->count());
    }
}
