<?php

namespace App\Enums;

enum SubscriptionProvider: string
{
    case Internal = 'internal';
    case AppStore = 'app_store';
    case GooglePlay = 'google_play';
    case Stripe = 'stripe';
    case External = 'external';
}
