<?php

namespace App\Services\Apple\Auth;

use Illuminate\Validation\ValidationException;

class AppleIdentityTokenVerifier
{
    public function __construct(private ApplePublicKeys $applePublicKeys) {}

    /** @return array<string, mixed> */
    public function verify(string $token, string $clientId): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->invalid();
        }
        [$header, $payload, $signature] = $parts;
        $headerData = $this->decodeJson($header);
        $claims = $this->decodeJson($payload);
        if (($headerData['alg'] ?? null) !== 'RS256' || ! is_string($headerData['kid'] ?? null)) {
            $this->invalid();
        }
        $verified = openssl_verify($header.'.'.$payload, $this->decode($signature), $this->applePublicKeys->publicKeyFor($headerData['kid']), OPENSSL_ALGO_SHA256);
        if ($verified !== 1 || ($claims['iss'] ?? null) !== 'https://appleid.apple.com' || ($claims['aud'] ?? null) !== $clientId || ! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] <= now()->getTimestamp()) {
            $this->invalid();
        }

        return $claims;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $value): array
    {
        $decoded = json_decode($this->decode($value), true);
        if (! is_array($decoded)) {
            $this->invalid();
        }

        return $decoded;
    }

    private function decode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $decoded = base64_decode($value.str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            $this->invalid();
        }

        return $decoded;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['authorization_code' => ['Apple returned an invalid identity token.']]);
    }
}
