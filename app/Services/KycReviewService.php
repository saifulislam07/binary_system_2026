<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Exceptions\MemberAdminException;
use App\Models\Admin;
use App\Models\KycDocument;
use Illuminate\Support\Facades\DB;

class KycReviewService
{
    public function approve(KycDocument $document, Admin $admin): KycDocument
    {
        return $this->decide($document, $admin, KycStatus::Approved, null);
    }

    public function reject(KycDocument $document, Admin $admin, string $reason): KycDocument
    {
        if (trim($reason) === '') {
            throw new MemberAdminException('A reason is required to reject KYC.');
        }

        return $this->decide($document, $admin, KycStatus::Rejected, $reason);
    }

    private function decide(KycDocument $document, Admin $admin, KycStatus $to, ?string $reason): KycDocument
    {
        return DB::transaction(function () use ($document, $admin, $to, $reason) {
            $document = KycDocument::query()->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== KycStatus::Pending) {
                throw new MemberAdminException("This KYC submission was already {$document->status->value}.");
            }

            $document->forceFill([
                'status' => $to,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            activity('kyc')
                ->performedOn($document)
                ->causedBy($admin)
                ->withProperties(['member_id' => $document->member_id, 'status' => $to->value, 'reason' => $reason])
                ->log("KYC {$to->value}");

            return $document;
        });
    }
}
