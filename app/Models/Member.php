<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A participant in the network. `sponsor` is who referred them (referral
 * bonus goes there); `placementParent` + `placement_side` is where they sit
 * in the binary tree (team volume flows up this chain). They can differ.
 *
 * @property MemberStatus $status
 * @property PlacementSide|null $placement_side
 */
#[Fillable([
    'user_id', 'member_code', 'sponsor_id', 'placement_parent_id', 'placement_side',
    'package_id', 'status', 'nid', 'address', 'activated_at',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'placement_side' => PlacementSide::class,
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id');
    }

    /** @return HasMany<Member, $this> */
    public function sponsoredMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function placementParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'placement_parent_id');
    }

    /** @return HasMany<Member, $this> */
    public function placementChildren(): HasMany
    {
        return $this->hasMany(Member::class, 'placement_parent_id');
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return HasOne<BinaryNode, $this> */
    public function binaryNode(): HasOne
    {
        return $this->hasOne(BinaryNode::class);
    }

    /** @return HasOne<Wallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /** @return HasMany<Bonus, $this> */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    /** @return HasMany<Withdrawal, $this> */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /** @return HasMany<WithdrawalMethod, $this> */
    public function withdrawalMethods(): HasMany
    {
        return $this->hasMany(WithdrawalMethod::class);
    }

    /** @return HasMany<KycDocument, $this> */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /** @return HasMany<TeamVolume, $this> */
    public function teamVolumes(): HasMany
    {
        return $this->hasMany(TeamVolume::class);
    }

    /** @return HasMany<RankAchievement, $this> */
    public function rankAchievements(): HasMany
    {
        return $this->hasMany(RankAchievement::class);
    }

    /** @return BelongsToMany<Rank, $this> */
    public function ranks(): BelongsToMany
    {
        return $this->belongsToMany(Rank::class, 'rank_achievements')
            ->withPivot('achieved_at')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Member>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }
}
