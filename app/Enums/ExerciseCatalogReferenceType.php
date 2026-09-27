<?php

namespace App\Enums;

enum ExerciseCatalogReferenceType: string
{
    case ExerciseCategories = 'exerciseCategories';
    case Goals = 'goals';
    case TrainingLevels = 'trainingLevels';
    case MovementPatterns = 'movementPatterns';
    case Equipment = 'equipment';
    case MovementRestrictions = 'movementRestrictions';
    case TrackingModes = 'trackingModes';
    case TargetMetrics = 'targetMetrics';
    case MajorMuscleGroups = 'majorMuscleGroups';
    case MuscleGroups = 'muscleGroups';

    public function icon(string $id): string
    {
        return $this->icons()[$id] ?? $this->fallbackIcon();
    }

    public function fallbackIcon(): string
    {
        return 'circle-alert';
    }

    /**
     * @return array<string, string>
     */
    private function icons(): array
    {
        return match ($this) {
            self::ExerciseCategories => [
                'strength' => 'dumbbell',
                'bodyweight' => 'person-standing',
                'cardio' => 'heart-pulse',
                'mobility' => 'move',
                'hiit' => 'zap',
            ],
            self::Goals => [
                'loseWeight' => 'scale',
                'buildMuscle' => 'dumbbell',
                'gainStrength' => 'trophy',
                'improveEndurance' => 'gauge',
                'improveMobility' => 'move',
                'bodyRecomposition' => 'rotate-ccw',
                'generalFitness' => 'activity',
            ],
            self::TrainingLevels => [
                'completeBeginner' => 'sprout',
                'beginner' => 'sprout',
                'novice' => 'activity',
                'intermediate' => 'gauge',
                'advanced' => 'trophy',
                'elite' => 'crown',
            ],
            self::MovementPatterns => [
                'squat' => 'arrow-down',
                'lunge' => 'footprints',
                'hinge' => 'rotate-ccw',
                'horizontalPush' => 'arrow-right',
                'verticalPush' => 'arrow-up',
                'horizontalPull' => 'arrow-left',
                'verticalPull' => 'arrow-down',
                'coreStability' => 'shield',
                'coreFlexion' => 'circle-dot',
                'coreRotation' => 'rotate-ccw',
                'locomotion' => 'footprints',
                'cyclicalCardio' => 'heart-pulse',
                'plyometric' => 'rabbit',
                'mobility' => 'move',
            ],
            self::Equipment => [
                'bodyweight' => 'person-standing',
                'resistanceBands' => 'activity',
                'dumbbells' => 'dumbbell',
                'kettlebells' => 'weight',
                'medicineBall' => 'circle-dot',
                'battleRopes' => 'activity',
                'jumpRope' => 'repeat',
                'suspensionTrainer' => 'activity',
                'gymnasticRings' => 'circle-dot',
                'cableMachine' => 'activity',
                'smithMachine' => 'dumbbell',
                'legPressMachine' => 'footprints',
                'chestPressMachine' => 'arrow-right',
                'legCurlMachine' => 'footprints',
                'rearDeltMachine' => 'arrow-left',
                'seatedRowMachine' => 'arrow-left',
                'gluteKickbackMachine' => 'footprints',
                'pecDeckMachine' => 'arrow-right',
                'hipAbductionMachine' => 'accessibility',
                'hipAdductionMachine' => 'accessibility',
                'rowingMachine' => 'route',
                'treadmill' => 'footprints',
                'stationaryBike' => 'bike',
                'elliptical' => 'activity',
                'stairClimber' => 'arrow-up',
                'skiErg' => 'dumbbell',
                'flatBench' => 'activity',
                'adjustableBench' => 'activity',
                'squatRack' => 'dumbbell',
                'olympicBarbell' => 'dumbbell',
                'ezBar' => 'dumbbell',
                'trapBar' => 'dumbbell',
                'deadliftPlatform' => 'weight',
                'pullUpBar' => 'arrow-up',
                'dipStation' => 'arrow-down',
                'plyoBox' => 'rabbit',
                'boxingBag' => 'target',
                'landmineAttachment' => 'target',
            ],
            self::MovementRestrictions => [
                'lowerBackPain' => 'accessibility',
                'shoulderPain' => 'accessibility',
                'kneePain' => 'accessibility',
                'hipPain' => 'accessibility',
                'anklePain' => 'accessibility',
                'wristPain' => 'accessibility',
                'elbowPain' => 'accessibility',
                'neckPain' => 'accessibility',
                'limitedShoulderMobility' => 'move',
                'limitedHipMobility' => 'move',
                'limitedAnkleMobility' => 'move',
                'limitedKneeFlexion' => 'move',
                'overheadMovementLimitation' => 'arrow-up',
                'impactSensitivity' => 'shield',
            ],
            self::TrackingModes => [
                'reps' => 'repeat',
                'durationSeconds' => 'timer',
                'durationMinutes' => 'clock',
                'durationMinutesAndDistance' => 'route',
            ],
            self::TargetMetrics => [
                'reps' => 'repeat',
                'weight' => 'weight',
                'durationSeconds' => 'timer',
                'durationMinutes' => 'clock',
                'distance' => 'ruler',
                'pace' => 'gauge',
            ],
            self::MajorMuscleGroups => [
                'chest' => 'shield',
                'back' => 'accessibility',
                'shoulders' => 'dumbbell',
                'arms' => 'dumbbell',
                'legs' => 'footprints',
                'core' => 'circle-dot',
                'other' => 'activity',
            ],
            self::MuscleGroups => [
                'chest' => 'shield',
                'back' => 'accessibility',
                'shoulders' => 'dumbbell',
                'biceps' => 'dumbbell',
                'triceps' => 'dumbbell',
                'forearms' => 'dumbbell',
                'quads' => 'footprints',
                'hamstrings' => 'footprints',
                'innerThigh' => 'footprints',
                'outerThigh' => 'footprints',
                'glutes' => 'footprints',
                'calves' => 'footprints',
                'abs' => 'circle-dot',
                'lowerAbs' => 'circle-dot',
                'upperAbs' => 'circle-dot',
                'obliques' => 'circle-dot',
                'lowerBack' => 'accessibility',
                'upperBack' => 'accessibility',
                'legs' => 'footprints',
                'hips' => 'accessibility',
                'cardio' => 'heart-pulse',
                'fullBody' => 'person-standing',
                'neck' => 'accessibility',
            ],
        };
    }
}
