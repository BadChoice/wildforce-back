<?php

namespace App\Enums;

enum CoachClientLimit
{
    case Five;
    case Thirty;
    case Unlimited;

    public function limit(): ?int
    {
        return match ($this) {
            self::Five => 5,
            self::Thirty => 30,
            self::Unlimited => null,
        };
    }
}
