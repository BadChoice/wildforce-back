<?php

namespace App\Services\GooglePlay;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlayPublisherClient
{
    /** @return array<string, mixed> */
    public function subscriptionPurchase(string $purchaseToken): array
    {
        $credentials = json_decode((string) config('services.google_play.service_account_json'), true);
        $packageName = config('services.google_play.package_name');

        if (! is_array($credentials) || ! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null) || ! is_string($packageName) || $packageName === '') {
            throw new RuntimeException('Google Play server verification is not configured.');
        }

        $assertion = $this->serviceAccountAssertion($credentials);
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw()->json();
        $accessToken = is_array($tokenResponse) ? $tokenResponse['access_token'] ?? null : null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google Play did not return an access token.');
        }

        $purchase = Http::withToken($accessToken)->get(
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($packageName).
            '/purchases/subscriptionsv2/tokens/'.rawurlencode($purchaseToken),
        )->throw()->json();

        if (! is_array($purchase)) {
            throw new RuntimeException('Google Play returned an invalid subscription purchase.');
        }

        return $purchase;
    }

    /** @param array<string, mixed> $credentials */
    private function serviceAccountAssertion(array $credentials): string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/androidpublisher',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$claims;
        if (! openssl_sign($unsignedToken, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Google Play credentials could not sign a request.');
        }

        return $unsignedToken.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
