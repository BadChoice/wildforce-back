<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Trial = 'trial';
    case CoachTrial = 'coach_trial';
    case Demo = 'demo';
    case Friends = 'friends';
    case Premium = 'premium';
    case CoachBasicMonthly = 'coach_basic_monthly';
    case CoachBasicYearly = 'coach_basic_yearly';
    case CoachProMonthly = 'coach_pro_monthly';
    case CoachProYearly = 'coach_pro_yearly';
    case CoachedExternal = 'coached_external';

    public function isTemporaryAccess(): bool
    {
        return in_array($this, [self::Trial, self::CoachTrial, self::Demo], true);
    }

    public function isCoachPlan(): bool
    {
        return in_array($this, self::coachPlans(), true);
    }

    /**
     * @return list<self>
     */
    public static function coachPlans(): array
    {
        return [
            self::CoachTrial,
            self::CoachBasicMonthly,
            self::CoachBasicYearly,
            self::CoachProMonthly,
            self::CoachProYearly,
        ];
    }
}
