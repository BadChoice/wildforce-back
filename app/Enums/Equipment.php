<?php

namespace App\Enums;

enum Equipment: string
{
    case Bodyweight = 'bodyweight';
    case ResistanceBands = 'resistanceBands';
    case Dumbbells = 'dumbbells';
    case Kettlebells = 'kettlebells';
    case MedicineBall = 'medicineBall';
    case BattleRopes = 'battleRopes';
    case JumpRope = 'jumpRope';
    case SuspensionTrainer = 'suspensionTrainer';
    case GymnasticRings = 'gymnasticRings';
    case CableMachine = 'cableMachine';
    case SmithMachine = 'smithMachine';
    case LegPressMachine = 'legPressMachine';
    case ChestPressMachine = 'chestPressMachine';
    case LegCurlMachine = 'legCurlMachine';
    case RearDeltMachine = 'rearDeltMachine';
    case SeatedRowMachine = 'seatedRowMachine';
    case GluteKickbackMachine = 'gluteKickbackMachine';
    case PecDeckMachine = 'pecDeckMachine';
    case HipAbductionMachine = 'hipAbductionMachine';
    case HipAdductionMachine = 'hipAdductionMachine';
    case RowingMachine = 'rowingMachine';
    case Treadmill = 'treadmill';
    case StationaryBike = 'stationaryBike';
    case Elliptical = 'elliptical';
    case StairClimber = 'stairClimber';
    case SkiErg = 'skiErg';
    case FlatBench = 'flatBench';
    case AdjustableBench = 'adjustableBench';
    case SquatRack = 'squatRack';
    case OlympicBarbell = 'olympicBarbell';
    case EzBar = 'ezBar';
    case TrapBar = 'trapBar';
    case DeadliftPlatform = 'deadliftPlatform';
    case PullUpBar = 'pullUpBar';
    case DipStation = 'dipStation';
    case PlyoBox = 'plyoBox';
    case BoxingBag = 'boxingBag';
    case LandmineAttachment = 'landmineAttachment';

    /**
     * @return list<string>
     */
    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
