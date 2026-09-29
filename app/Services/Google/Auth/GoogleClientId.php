<?php

namespace App\Services\Google\Auth;

use RuntimeException;

class GoogleClientId
{
    public function value(): string
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('The Google client ID configuration is missing.');
        }

        return $clientId;
    }
}
