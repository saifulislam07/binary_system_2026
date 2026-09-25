<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Rule #9: pending → approved → processing → paid, or → rejected from
 * pending/approved. Paid and rejected are final.
 */
enum WithdrawalStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Processing, self::Rejected],
            self::Processing => [self::Paid],
            self::Paid, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Still holding the member's funds.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Paid, self::Rejected], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending · অপেক্ষমাণ',
            self::Approved => 'Approved · অনুমোদিত',
            self::Processing => 'Processing · প্রক্রিয়াধীন',
            self::Paid => 'Paid · পরিশোধিত',
            self::Rejected => 'Rejected · বাতিল',
        };
    }
}
