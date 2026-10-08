<?php

namespace App\Enums;

enum ExerciseFeedback: string
{
    case VeryEasy = 'veryEasy';
    case Easy = 'easy';
    case JustRight = 'justRight';
    case Hard = 'hard';
    case VeryHard = 'veryHard';
}
