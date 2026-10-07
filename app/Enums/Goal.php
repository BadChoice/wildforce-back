<?php

namespace App\Enums;

enum Goal: string
{
    case LoseWeight = 'loseWeight';
    case BuildMuscle = 'buildMuscle';
    case GainStrength = 'gainStrength';
    case ImproveEndurance = 'improveEndurance';
    case ImproveMobility = 'improveMobility';
    case BodyRecomposition = 'bodyRecomposition';
    case GeneralFitness = 'generalFitness';

    /**
     * @return list<string>
     */
    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
