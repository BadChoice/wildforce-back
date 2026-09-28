<?php

namespace App\Services\Apple\Auth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApplePublicKeys
{
    public function publicKeyFor(string $keyId): string
    {
        $key = Arr::first($this->keys(), fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? null) === $keyId);
        if ($key === null) {
            Cache::forget('apple-sign-in-public-keys');
            $key = Arr::first($this->keys(), fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? null) === $keyId);
        }
        if (! is_array($key) || ($key['kty'] ?? null) !== 'EC' || ($key['crv'] ?? null) !== 'P-256') {
            throw ValidationException::withMessages(['authorization_code' => ['Apple returned an unknown signing key.']]);
        }
        $x = $this->decode((string) ($key['x'] ?? ''));
        $y = $this->decode((string) ($key['y'] ?? ''));
        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw ValidationException::withMessages(['authorization_code' => ['Apple returned an invalid signing key.']]);
        }
        $algorithm = hex2bin('301306072A8648CE3D020106082A8648CE3D030107');
        $spki = "\x30\x59".$algorithm."\x03\x42\x00\x04".$x.$y;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    /** @return array<int, mixed> */
    private function keys(): array
    {
        return Cache::remember('apple-sign-in-public-keys', now()->addHour(), function (): array {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->get('https://appleid.apple.com/auth/keys');
            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new RuntimeException('Apple signing keys could not be retrieved.');
            }

            return $response->json('keys');
        });
    }

    private function decode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $decoded = base64_decode($value.str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw ValidationException::withMessages(['authorization_code' => ['Apple returned an invalid signing key.']]);
        }

        return $decoded;
    }
}
