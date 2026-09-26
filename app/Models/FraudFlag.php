<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something that needs a human look. Flags never block anything on their
 * own; an admin reviews or dismisses them.
 *
 * @property array<string, mixed> $details
 * @property CarbonInterface|null $reviewed_at
 */
#[Fillable(['member_id', 'type', 'subject_type', 'subject_id', 'details'])]
class FraudFlag extends Model
{
    public const RAPID_WITHDRAWAL = 'rapid_withdrawal';

    public const WITHDRAWAL_EXCEEDS_EARNINGS = 'withdrawal_exceeds_earnings';

    public const ACTIVATION_BLOCKED_DUPLICATE = 'activation_blocked_duplicate';

    public const LABELS = [
        self::RAPID_WITHDRAWAL => 'Withdrawal soon after joining',
        self::WITHDRAWAL_EXCEEDS_EARNINGS => 'Withdrawals exceed earned income',
        self::ACTIVATION_BLOCKED_DUPLICATE => 'Paid, but activation blocked (duplicate mobile/NID)',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Raise a flag once; repeated scans of the same thing are no-ops.
     *
     * @param  array<string, mixed>  $details
     */
    public static function raise(Member $member, string $type, ?Model $subject, array $details): self
    {
        return static::query()->firstOrCreate(
            [
                'member_id' => $member->id,
                'type' => $type,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ],
            ['details' => $details],
        );
    }
}
