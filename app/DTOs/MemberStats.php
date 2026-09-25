<?php

namespace App\DTOs;

/**
 * The numbers rank and threshold-bonus rules are judged on.
 * Money in poysha; team sales = both legs' lifetime BV on the poysha scale.
 */
final readonly class MemberStats
{
    public function __construct(
        public int $personalSales,
        public int $teamSales,
        public int $activeTeam,
    ) {}
}
