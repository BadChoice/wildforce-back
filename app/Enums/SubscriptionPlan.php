<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Trial = 'trial';
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
        return in_array($this, [self::Trial, self::Demo], true);
    }
}
