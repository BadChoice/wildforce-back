<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
