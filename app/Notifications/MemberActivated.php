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
        return __('Your account is active');
    }

    public function message(object $notifiable): string
    {
        $parent = $this->member->placementParent()->value('member_code');
        $placement = $parent !== null && $this->member->placement_side !== null
            ? ' '.__('You are placed on the :side of :parent.', ['side' => __($this->member->placement_side->value), 'parent' => $parent])
            : '';

        return __('Your member ID is :code.', ['code' => $this->member->member_code])
            .$placement.' '.__('Share your referral link to build your team.');
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
