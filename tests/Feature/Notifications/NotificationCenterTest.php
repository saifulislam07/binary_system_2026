<?php

namespace Tests\Feature\Notifications;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Member::factory()->active()->create()->user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, array $data = [], bool $read = false, int $minutesAgo = 0): DatabaseNotification
    {
        return DatabaseNotification::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => ['kind' => 'income', 'title' => 'Referral bonus received · রেফারেল বোনাস', 'message' => '৳50.00 was added to your wallet.', 'url' => '/income', ...$data],
            'read_at' => $read ? now() : null,
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_the_unread_count_is_shared_with_every_page()
    {
        $this->notify($this->user);
        $this->notify($this->user);
        $this->notify($this->user, read: true);

        $this->actingAs($this->user)
            ->get(route('wallet.index'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 2));
    }

    public function test_the_bell_gets_the_latest_eight_newest_first()
    {
        foreach (range(1, 10) as $i) {
            $this->notify($this->user, ['title' => "Item {$i}"], minutesAgo: 100 - $i);
        }
        $this->notify(Member::factory()->active()->create()->user, ['title' => 'Someone else']);

        $this->actingAs($this->user)
            ->getJson(route('notifications.recent'))
            ->assertOk()
            ->assertJsonPath('unread', 10)
            ->assertJsonCount(8, 'items')
            ->assertJsonPath('items.0.title', 'Item 10')
            ->assertJsonPath('items.0.kind', 'income')
            ->assertJsonPath('items.0.read', false)
            ->assertJsonMissing(['title' => 'Someone else']);
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link()
    {
        $notification = $this->notify($this->user);

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect('/income');

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_links_to_other_sites_are_never_followed()
    {
        $offsite = $this->notify($this->user, ['url' => 'https://evil.example/phish']);
        $protocolRelative = $this->notify($this->user, ['url' => '//evil.example/phish']);

        $this->actingAs($this->user)->from('/dashboard')
            ->post(route('notifications.read', $offsite->id))
            ->assertRedirect('/dashboard');

        $this->actingAs($this->user)->from('/dashboard')
            ->post(route('notifications.read', $protocolRelative->id))
            ->assertRedirect('/dashboard');
    }

    public function test_members_cannot_touch_each_others_notifications()
    {
        $theirs = $this->notify(Member::factory()->active()->create()->user);

        $this->actingAs($this->user)
            ->post(route('notifications.read', $theirs->id))
            ->assertNotFound();

        $this->assertNull($theirs->fresh()?->read_at);
    }

    public function test_mark_all_read()
    {
        $this->notify($this->user);
        $this->notify($this->user);
        $theirs = $this->notify(Member::factory()->active()->create()->user);

        $this->actingAs($this->user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertNull($theirs->fresh()?->read_at);
    }

    public function test_the_full_list_page()
    {
        $this->notify($this->user, ['title' => 'Withdrawal paid · উত্তোলন পরিশোধিত', 'kind' => 'withdrawal']);
        $this->notify($this->user, read: true, minutesAgo: 5);

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->where('unread', 1)
                ->has('notifications.data', 2)
                ->where('notifications.data.0.title', 'Withdrawal paid · উত্তোলন পরিশোধিত')
                ->where('notifications.data.0.kind', 'withdrawal')
                ->where('notifications.data.1.read', true));
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->getJson(route('notifications.recent'))->assertUnauthorized();
    }
}
