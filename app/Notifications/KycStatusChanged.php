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
            KycStatus::Approved => 'KYC approved · কেওয়াইসি অনুমোদিত',
            KycStatus::Rejected => 'KYC rejected · কেওয়াইসি বাতিল',
            KycStatus::Pending => 'KYC submitted · কেওয়াইসি জমা হয়েছে',
        };
    }

    public function message(object $notifiable): string
    {
        return match ($this->status) {
            KycStatus::Approved => 'Your identity documents were verified.',
            KycStatus::Rejected => "Your identity documents were not accepted: {$this->document->rejection_reason}. Please submit them again.",
            KycStatus::Pending => 'Your identity documents are waiting for review.',
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
