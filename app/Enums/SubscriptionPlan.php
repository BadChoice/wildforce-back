<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Trial = 'trial';
    case CoachTrial = 'coach_trial';
    case Demo = 'demo';
    case Friends = 'friends';
    case Premium = 'premium';
    case CoachBasic = 'coach_basic';
    case CoachStudio = 'coach_studio';
    case CoachPro = 'coach_pro';
    case CoachedExternal = 'coached_external';

    public function isTemporaryAccess(): bool
    {
        return in_array($this, [self::Trial, self::CoachTrial, self::Demo], true);
    }

    public function isCoachPlan(): bool
    {
        return $this->coachClientLimit() !== null;
    }

    /**
     * @return list<self>
     */
    public static function coachPlans(): array
    {
        return [
            self::CoachTrial,
            self::CoachBasic,
            self::CoachStudio,
            self::CoachPro,
        ];
    }

    public function coachClientLimit(): ?CoachClientLimit
    {
        return match ($this) {
            self::CoachTrial,
            self::CoachBasic => CoachClientLimit::Five,
            self::CoachStudio => CoachClientLimit::Thirty,
            self::CoachPro => CoachClientLimit::Unlimited,
            default => null,
        };
    }
}
