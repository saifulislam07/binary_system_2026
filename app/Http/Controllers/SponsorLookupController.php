<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Http\JsonResponse;

/**
 * Lets the registration form confirm a sponsor ID before submitting.
 * Returns only the display name — nothing else about the member.
 */
class SponsorLookupController extends Controller
{
    public function __invoke(string $code): JsonResponse
    {
        $member = Member::query()
            ->with('user:id,name')
            ->where('member_code', strtoupper($code))
            ->where('status', MemberStatus::Active)
            ->first();

        if ($member === null) {
            return response()->json(['message' => __('No active member has this sponsor ID.')], 404);
        }

        return response()->json([
            'code' => $member->member_code,
            'name' => $member->user->name,
        ]);
    }
}
