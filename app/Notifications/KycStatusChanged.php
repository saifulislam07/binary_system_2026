<?php

namespace App\Notifications;

use App\Enums\KycStatus;
use App\Models\KycDocument;

class KycStatusChanged extends MemberNotification
{
    public KycStatus $status;

    public function __construct(public KycDocument $document)
    {
        parent::__construct();

        $this->status = $document->status;
    }

    public function kind(): string
    {
        return 'kyc';
    }

    public function title(): string
    {
        return match ($this->status) {
            KycStatus::Approved => __('KYC approved'),
            KycStatus::Rejected => __('KYC rejected'),
            KycStatus::Pending => __('KYC submitted'),
        };
    }

    public function message(object $notifiable): string
    {
        return match ($this->status) {
            KycStatus::Approved => __('Your identity documents were verified.'),
            KycStatus::Rejected => __('Your identity documents were not accepted: :reason. Please submit them again.', ['reason' => $this->document->rejection_reason]),
            KycStatus::Pending => __('Your identity documents are waiting for review.'),
        };
    }

    public function path(): string
    {
        return route('kyc.index', absolute: false);
    }

    protected function data(): array
    {
        return ['status' => $this->status->value];
    }
}
