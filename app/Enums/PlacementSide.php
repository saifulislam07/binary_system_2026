<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PlacementSide: string
{
    use HasValues;

    case Left = 'left';
    case Right = 'right';

    public function opposite(): self
    {
        return $this === self::Left ? self::Right : self::Left;
    }

    /**
     * Column on binary_nodes holding the child for this side.
     */
    public function childColumn(): string
    {
        return $this->value.'_child_id';
    }

    /**
     * Column on binary_nodes holding the current-cycle volume for this side.
     */
    public function volumeColumn(): string
    {
        return $this->value.'_volume';
    }
}
