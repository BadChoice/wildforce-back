<?php

namespace App\Services\Google\Auth;

use RuntimeException;

class GoogleClientId
{
    public function accepts(mixed $audience): bool
    {
        if (! is_string($audience)) {
            return false;
        }

        $clientIds = array_filter([
            config('services.google.client_id'),
            config('services.google.web_client_id'),
        ], fn (mixed $clientId): bool => is_string($clientId) && $clientId !== '');

        if ($clientIds === []) {
            throw new RuntimeException('The Google client ID configuration is missing.');
        }

        return in_array($audience, $clientIds, true);
    }
}
