<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user('web')->member;
        $url = route('register', ['ref' => $member->member_code]);
        $message = "Join me — register with my sponsor ID {$member->member_code}: {$url}";

        return Inertia::render('referral/Index', [
            'code' => $member->member_code,
            'url' => $url,
            'shareLinks' => [
                'whatsapp' => 'https://wa.me/?text='.rawurlencode($message),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
            ],
            'referrals' => [
                'total' => $member->sponsoredMembers()->count(),
                'active' => $member->sponsoredMembers()->where('status', MemberStatus::Active)->count(),
            ],
        ]);
    }
}
