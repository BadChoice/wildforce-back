<?php

namespace App\Enums;

enum MesocyclePhase: string
{
    case Accumulation = 'accumulation';
    case Intensification = 'intensification';
    case Deload = 'deload';
}
