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
        return __('Registration received');
    }

    public function message(object $notifiable): string
    {
        $package = $this->member->package()->value('name');

        return $package !== null
            ? __('Welcome! Your account is created. Complete the payment for the :package package to activate it and get your member ID.', ['package' => $package])
            : __('Welcome! Your account is created. Complete the payment to activate it and get your member ID.');
    }

    public function path(): string
    {
        return route('checkout.index', absolute: false);
    }

    public function actionText(): string
    {
        return __('Complete payment');
    }
}
