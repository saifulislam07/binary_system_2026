<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin broadcast to a segment of members. Sent by AnnouncementService.
 *
 * @property list<string> $channels
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['admin_id', 'title', 'body', 'audience', 'min_rank_id', 'package_id', 'channels'])]
class Announcement extends Model
{
    public const AUDIENCES = [
        'active' => 'Active members',
        'pending' => 'Pending (not yet paid)',
        'suspended' => 'Suspended members',
        'all' => 'Everyone',
    ];

    public const CHANNELS = ['mail' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return BelongsTo<Rank, $this> */
    public function minRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'min_rank_id');
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
