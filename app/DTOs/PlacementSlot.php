<?php

namespace App\DTOs;

use App\Enums\PlacementSide;
use App\Models\Member;

/**
 * A vacant position in the binary tree: the empty `$side` child slot of `$parent`.
 */
final readonly class PlacementSlot
{
    public function __construct(
        public Member $parent,
        public PlacementSide $side,
    ) {}
}
