<?php

namespace App\Notifications;

use App\Models\Member;

class RegistrationReceived extends MemberNotification
{
    public function __construct(public Member $member)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'registration';
    }

    public function title(): string
    {
        return 'Registration received · নিবন্ধন সম্পন্ন';
    }

    public function message(object $notifiable): string
    {
        $package = $this->member->package()->value('name');

        return 'Welcome! Your account is created. Complete the payment'
            .($package !== null ? " for the {$package} package" : '')
            .' to activate it and get your member ID.';
    }

    public function path(): string
    {
        return route('checkout.index', absolute: false);
    }

    public function actionText(): string
    {
        return 'Complete payment · পেমেন্ট করুন';
    }
}
