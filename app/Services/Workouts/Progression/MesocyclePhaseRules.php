<?php

namespace App\Services\Workouts\Progression;

use App\Enums\MesocyclePhase;

final class MesocyclePhaseRules
{
    /**
     * @return array{phase: MesocyclePhase, weekInPhase: int, cycleLength: int, positionInCycle: int}|null
     */
    public static function phaseInfo(int $mesocycleNumber, string $trainingLevel): ?array
    {
        $cycleLength = self::cycleLength($trainingLevel);

        return $cycleLength === null
            ? null
            : self::phaseInfoForCycleLength($mesocycleNumber, $cycleLength);
    }

    public static function cycleLength(string $trainingLevel): ?int
    {
        return match ($trainingLevel) {
            'completeBeginner' => null,
            'beginner', 'novice' => 4,
            'intermediate' => 6,
            'advanced' => 8,
            'elite' => 10,
            default => null,
        };
    }

    /**
     * @return array{phase: MesocyclePhase, weekInPhase: int, cycleLength: int, positionInCycle: int}|null
     */
    public static function phaseInfoForCycleLength(int $mesocycleNumber, int $cycleLength): ?array
    {
        if ($mesocycleNumber < 1 || $cycleLength < 2) {
            return null;
        }

        $accumulationLength = self::phaseLength(MesocyclePhase::Accumulation, $cycleLength);
        $intensificationLength = self::phaseLength(MesocyclePhase::Intensification, $cycleLength);
        $positionInCycle = (($mesocycleNumber - 1) % $cycleLength) + 1;

        if ($positionInCycle <= $accumulationLength) {
            return [
                'phase' => MesocyclePhase::Accumulation,
                'weekInPhase' => $positionInCycle,
                'cycleLength' => $cycleLength,
                'positionInCycle' => $positionInCycle,
            ];
        }

        if ($intensificationLength > 0 && $positionInCycle <= $accumulationLength + $intensificationLength) {
            return [
                'phase' => MesocyclePhase::Intensification,
                'weekInPhase' => $positionInCycle - $accumulationLength,
                'cycleLength' => $cycleLength,
                'positionInCycle' => $positionInCycle,
            ];
        }

        return [
            'phase' => MesocyclePhase::Deload,
            'weekInPhase' => 1,
            'cycleLength' => $cycleLength,
            'positionInCycle' => $positionInCycle,
        ];
    }

    /**
     * Number of weeks the given phase lasts within a cycle of the given length.
     */
    public static function phaseLength(MesocyclePhase $phase, int $cycleLength): int
    {
        $accumulationLength = $cycleLength <= 4 ? $cycleLength - 1 : intdiv($cycleLength, 2);

        return match ($phase) {
            MesocyclePhase::Accumulation => $accumulationLength,
            MesocyclePhase::Intensification => max($cycleLength - $accumulationLength - 1, 0),
            MesocyclePhase::Deload => 1,
        };
    }

    public static function mesocycleIndex(int $mesocycleNumber, int $cycleLength): ?int
    {
        if ($mesocycleNumber < 1 || $cycleLength < 1) {
            return null;
        }

        return intdiv($mesocycleNumber - 1, $cycleLength) + 1;
    }
}
