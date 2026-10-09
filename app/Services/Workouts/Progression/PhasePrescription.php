<?php

namespace App\Services\Workouts\Progression;

use App\Enums\MesocyclePhase;

/**
 * Turns a mesocycle phase into explicit, numeric training parameters for the planner prompt.
 */
final class PhasePrescription
{
    /**
     * @param  array{phase: MesocyclePhase, weekInPhase: int, cycleLength: int, positionInCycle: int}|null  $phaseInfo
     */
    public static function describe(?array $phaseInfo, ?string $goal, ?string $bodyCompositionPhase): string
    {
        if ($phaseInfo === null) {
            return '- Not periodized: build sustainable technique and consistency before increasing load or volume. Keep 2-3 reps in reserve on every working set.';
        }

        $parameters = self::parameters($phaseInfo['phase'], $goal === 'gainStrength');
        $phaseLength = MesocyclePhaseRules::phaseLength($phaseInfo['phase'], $phaseInfo['cycleLength']);
        $isFinalWeek = $phaseInfo['weekInPhase'] >= $phaseLength;
        $mainRir = self::rirForWeek($parameters['mainRir'], $phaseInfo['weekInPhase'], $phaseLength);
        $accessoryRir = self::rirForWeek($parameters['accessoryRir'], $phaseInfo['weekInPhase'], $phaseLength);

        if ($bodyCompositionPhase === 'cut') {
            $mainRir = max($mainRir, 1);
        }

        $lines = [
            'Main lifts: '.$parameters['mainSets'].' sets × '.$parameters['mainReps'].' reps'.($parameters['mainIntensity'] === null ? '' : ' @ '.$parameters['mainIntensity']).', target RIR '.$mainRir.', rest '.$parameters['mainRest'].'.',
            'Accessories: '.$parameters['accessorySets'].' sets × '.$parameters['accessoryReps'].' reps, target RIR '.$accessoryRir.', rest '.$parameters['accessoryRest'].'.',
            'Volume: '.$parameters['volume'],
            'Load: '.$parameters['load'],
        ];

        if ($phaseInfo['phase'] !== MesocyclePhase::Deload && $phaseInfo['weekInPhase'] > 1) {
            $lines[] = 'Within-phase progression: keep last week\'s exercise selection where possible and progress load or reps; the lower RIR target reflects the later week in the phase.';
        }

        $bodyCompositionNote = match ($bodyCompositionPhase) {
            'cut' => 'Calorie deficit: do not add sets beyond the phase volume, keep loads rather than chasing new maximums, and avoid training main lifts to failure.',
            'bulk' => 'Calorie surplus: volume may sit at the upper end of the phase range when readiness allows.',
            default => null,
        };

        if ($bodyCompositionNote !== null) {
            $lines[] = $bodyCompositionNote;
        }

        if ($isFinalWeek && $phaseInfo['phase'] === MesocyclePhase::Intensification) {
            $lines[] = 'Next week is a deload, so this week can be the hardest of the cycle.';
        }

        return collect($lines)->map(fn (string $line): string => '- '.$line)->implode("\n");
    }

    /**
     * @return array{mainSets: string, mainReps: string, mainIntensity: ?string, mainRir: array{int, int}, mainRest: string, accessorySets: string, accessoryReps: string, accessoryRir: array{int, int}, accessoryRest: string, volume: string, load: string}
     */
    private static function parameters(MesocyclePhase $phase, bool $isStrengthGoal): array
    {
        return match ($phase) {
            MesocyclePhase::Accumulation => [
                'mainSets' => '3-4',
                'mainReps' => $isStrengthGoal ? '5-8' : '8-12',
                'mainIntensity' => $isStrengthGoal ? '75-80% 1RM' : null,
                'mainRir' => [3, 2],
                'mainRest' => $isStrengthGoal ? '2-3 min' : '2 min',
                'accessorySets' => '2-4',
                'accessoryReps' => $isStrengthGoal ? '8-12' : '10-15',
                'accessoryRir' => [3, 1],
                'accessoryRest' => '60-90 s',
                'volume' => 'highest volume of the cycle. After the first week, add at most one set to one or two exercises per muscle group when readiness is not low.',
                'load' => 'moderate. Progress load only once the top of the rep range is reached at the target RIR.',
            ],
            MesocyclePhase::Intensification => [
                'mainSets' => '3',
                'mainReps' => $isStrengthGoal ? '2-5' : '6-10',
                'mainIntensity' => $isStrengthGoal ? '82-92% 1RM' : null,
                'mainRir' => [2, 1],
                'mainRest' => $isStrengthGoal ? '3-5 min' : '2-3 min',
                'accessorySets' => '2-3',
                'accessoryReps' => $isStrengthGoal ? '6-10' : '8-12',
                'accessoryRir' => [1, 0],
                'accessoryRest' => '90 s',
                'volume' => 'about 20-30% fewer total working sets than the accumulation phase.',
                'load' => 'heavier than accumulation for the same exercises, following the lower rep range.',
            ],
            MesocyclePhase::Deload => [
                'mainSets' => '2',
                'mainReps' => $isStrengthGoal ? '3-5' : '8-10',
                'mainIntensity' => $isStrengthGoal ? '65-75% 1RM' : null,
                'mainRir' => [4, 4],
                'mainRest' => '2 min',
                'accessorySets' => '1-2',
                'accessoryReps' => '10-12',
                'accessoryRir' => [4, 4],
                'accessoryRest' => '60-90 s',
                'volume' => 'about 40-50% fewer working sets than last week. Keep the same exercises; use only straight sets.',
                'load' => 'about 85-90% of the most recent non-deload working load.',
            ],
        };
    }

    /**
     * @param  array{int, int}  $range  RIR at the first and the final week of the phase
     */
    private static function rirForWeek(array $range, int $weekInPhase, int $phaseLength): int
    {
        [$firstWeekRir, $finalWeekRir] = $range;

        if ($phaseLength <= 1) {
            return $finalWeekRir;
        }

        $progress = (min($weekInPhase, $phaseLength) - 1) / ($phaseLength - 1);

        return (int) round($firstWeekRir + ($finalWeekRir - $firstWeekRir) * $progress);
    }
}
