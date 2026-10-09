<?php

namespace App\Enums;

enum ExerciseStatus: string
{
    case Reduce = 'reduce';
    case Rotate = 'rotate';
    case Progress = 'progress';
    case Keep = 'keep';
}
