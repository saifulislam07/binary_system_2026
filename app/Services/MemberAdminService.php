<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Exceptions\MemberAdminException;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

/**
 * Admin changes to a member. Every method writes an activity-log entry with
 * the admin as causer and the before/after values (rule #12).
 */
class MemberAdminService
{
    /**
     * @param  array{name: string, email: string, phone: string, nid: string, address: string}  $data
     */
    public function update(Member $member, array $data, Admin $admin): Member
    {
        return DB::transaction(function () use ($member, $data, $admin) {
            $member = Member::query()->with('user')->lockForUpdate()->findOrFail($member->id);
            $before = $this->snapshot($member);

            $member->user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ])->save();
            $member->fill(['nid' => $data['nid'], 'phone' => $data['phone'], 'address' => $data['address']])->save();

            $after = $this->snapshot($member->refresh()->load('user'));
            $changed = array_keys(array_diff_assoc($after, $before));

            if ($changed !== []) {
                activity('members')
                    ->performedOn($member)
                    ->causedBy($admin)
                    ->withProperties([
                        'old' => array_intersect_key($before, array_flip($changed)),
                        'attributes' => array_intersect_key($after, array_flip($changed)),
                    ])
                    ->log('Member profile updated by admin');
            }

            return $member;
        });
    }

    public function suspend(Member $member, Admin $admin, string $reason): Member
    {
        return $this->changeStatus($member, $admin, MemberStatus::Active, MemberStatus::Suspended, $reason, 'Member suspended');
    }

    /**
     * Suspended → active. Only members who were activated before (have a code
     * and a tree position) can be reinstated; others still need to pay.
     */
    public function reinstate(Member $member, Admin $admin, string $reason): Member
    {
        if ($member->member_code === null) {
            throw new MemberAdminException('This member was never activated; they must complete payment first.');
        }

        return $this->changeStatus($member, $admin, MemberStatus::Suspended, MemberStatus::Active, $reason, 'Member reinstated');
    }

    /**
     * Changes the member's package label only. It creates no sale, BV or
     * commission — record a real purchase through checkout for that.
     */
    public function changePackage(Member $member, Package $package, Admin $admin, string $reason): Member
    {
        return DB::transaction(function () use ($member, $package, $admin, $reason) {
            $member = Member::query()->with('package')->lockForUpdate()->findOrFail($member->id);
            $from = $member->package;

            if ($from?->is($package)) {
                throw new MemberAdminException('The member already has this package.');
            }

            $member->forceFill(['package_id' => $package->id])->save();

            activity('members')
                ->performedOn($member)
                ->causedBy($admin)
                ->withProperties([
                    'old' => ['package' => $from?->name],
                    'attributes' => ['package' => $package->name],
                    'reason' => $reason,
                ])
                ->log('Member package changed by admin');

            return $member;
        });
    }

    private function changeStatus(Member $member, Admin $admin, MemberStatus $from, MemberStatus $to, string $reason, string $description): Member
    {
        if (trim($reason) === '') {
            throw new MemberAdminException('A reason is required.');
        }

        return DB::transaction(function () use ($member, $admin, $from, $to, $reason, $description) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($member->status !== $from) {
                throw new MemberAdminException("Only {$from->value} members can be changed to {$to->value}; this member is {$member->status->value}.");
            }

            $member->forceFill(['status' => $to])->save();

            activity('members')
                ->performedOn($member)
                ->causedBy($admin)
                ->withProperties([
                    'old' => ['status' => $from->value],
                    'attributes' => ['status' => $to->value],
                    'reason' => $reason,
                ])
                ->log($description);

            return $member;
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(Member $member): array
    {
        return [
            'name' => $member->user->name,
            'email' => $member->user->email,
            'phone' => $member->user->phone,
            'nid' => $member->nid,
            'address' => $member->address,
        ];
    }
}
