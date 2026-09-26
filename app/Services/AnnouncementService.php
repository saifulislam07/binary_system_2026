<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Jobs\SendAnnouncement;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\Rank;
use App\Models\User;
use App\Notifications\ImportantAnnouncement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * "Important Announcement" broadcasts to a segment of members.
 */
class AnnouncementService
{
    /**
     * Save the announcement and queue its delivery (after commit).
     *
     * @param  array<string, mixed>  $data  validated StoreAnnouncementRequest input
     */
    public function publish(Admin $admin, array $data): Announcement
    {
        if (! $admin->hasPermissionTo(AdminPermission::SendAnnouncements->value, 'admin')) {
            throw new AuthorizationException('This admin may not send announcements.');
        }

        return DB::transaction(function () use ($admin, $data) {
            $announcement = Announcement::query()->create([
                'admin_id' => $admin->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'min_rank_id' => $data['min_rank_id'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'channels' => array_values(array_intersect(array_keys(Announcement::CHANNELS), (array) ($data['channels'] ?? []))),
            ]);

            $announcement->forceFill(['recipients' => $this->recipients($announcement)->count()])->save();

            activity('announcements')
                ->performedOn($announcement)
                ->causedBy($admin)
                ->withProperties([
                    'audience' => $announcement->audience,
                    'min_rank_id' => $announcement->min_rank_id,
                    'package_id' => $announcement->package_id,
                    'channels' => $announcement->channels,
                    'recipients' => $announcement->recipients,
                ])
                ->log('Announcement sent');

            SendAnnouncement::dispatch($announcement)->afterCommit();

            return $announcement;
        });
    }

    /**
     * Notify every member in the segment. Runs from the queued job; a second
     * run of the same announcement does nothing.
     */
    public function deliver(Announcement $announcement): int
    {
        $announcement = Announcement::query()->findOrFail($announcement->id);

        if ($announcement->sent_at !== null) {
            return 0;
        }

        $sent = 0;

        $this->recipients($announcement)->chunkById((int) config('notifications.broadcast_chunk', 500), function (Collection $users) use ($announcement, &$sent) {
            Notification::send($users, new ImportantAnnouncement($announcement));
            $sent += $users->count();
        });

        $announcement->forceFill(['sent_at' => now(), 'recipients' => $sent])->save();

        return $sent;
    }

    /**
     * Login accounts of the members in the announcement's segment.
     *
     * @return Builder<User>
     */
    public function recipients(Announcement $announcement): Builder
    {
        return User::query()->whereHas('member', function (Builder $member) use ($announcement) {
            if ($announcement->audience !== 'all') {
                $member->where('status', $announcement->audience);
            }

            if ($announcement->package_id !== null) {
                $member->where('package_id', $announcement->package_id);
            }

            if ($announcement->min_rank_id !== null) {
                $this->atOrAboveRank($member, Rank::query()->findOrFail($announcement->min_rank_id));
            }
        });
    }

    /**
     * @param  Builder<Member>  $member
     */
    private function atOrAboveRank(Builder $member, Rank $rank): void
    {
        $ids = Rank::query()->where('sort_order', '>=', $rank->sort_order)->pluck('id');
        $isLowest = ! Rank::query()->where('sort_order', '<', $rank->sort_order)->exists();

        // Members never promoted yet have no current rank: they sit at the lowest one.
        $member->where(fn (Builder $q) => $q->whereIn('current_rank_id', $ids)
            ->when($isLowest, fn (Builder $q) => $q->orWhereNull('current_rank_id')));
    }
}
