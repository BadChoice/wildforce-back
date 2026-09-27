<?php

namespace App\Enums;

enum CoachingEnrollmentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
}
