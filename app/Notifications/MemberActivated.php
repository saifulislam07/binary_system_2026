<?php

namespace App\Notifications;

use App\Models\Member;

class MemberActivated extends MemberNotification
{
    public function __construct(public Member $member)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'activation';
    }

    public function title(): string
    {
        return 'Your account is active · আপনার অ্যাকাউন্ট সক্রিয়';
    }

    public function message(object $notifiable): string
    {
        $parent = $this->member->placementParent()->value('member_code');
        $placement = $parent !== null && $this->member->placement_side !== null
            ? " You are placed on the {$this->member->placement_side->value} of {$parent}."
            : '';

        return "Your member ID is {$this->member->member_code}.{$placement} Share your referral link to build your team.";
    }

    public function path(): string
    {
        return route('dashboard', absolute: false);
    }

    protected function urgent(): bool
    {
        return true;
    }

    protected function data(): array
    {
        return ['member_code' => $this->member->member_code];
    }
}
