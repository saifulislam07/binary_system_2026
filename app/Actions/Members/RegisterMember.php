<?php

namespace App\Actions\Members;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the login account and a *pending* member. No member code and no
 * tree placement yet — both happen in PlacementService::activateMember()
 * once the package is paid for.
 */
class RegisterMember
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string, nid: string, address: string, sponsor_id: int, preferred_side: PlacementSide, package_id: int}  $data
     */
    public function handle(array $data, ?string $ip = null, ?string $device = null): Member
    {
        return DB::transaction(function () use ($data, $ip, $device) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'ip_registered' => $ip,
                'device_registered' => $device === null ? null : mb_substr($device, 0, 255),
            ]);
            $user->assignRole('member');

            $member = $user->member()->create([
                'sponsor_id' => $data['sponsor_id'],
                'preferred_side' => $data['preferred_side'],
                'package_id' => $data['package_id'],
                'nid' => $data['nid'],
                'address' => $data['address'],
                'status' => MemberStatus::Pending,
            ]);

            activity('registration')
                ->performedOn($member)
                ->causedBy($user)
                ->withProperties(['ip' => $ip, 'device' => $device, 'sponsor_id' => $data['sponsor_id']])
                ->log('Member registered');

            return $member;
        });
    }
}
