<?php

namespace App\Services\Google\Auth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GooglePublicKeys
{
    private const CacheKey = 'google-sign-in-public-keys';

    private const KeysEndpoint = 'https://www.googleapis.com/oauth2/v3/certs';

    public function publicKeyFor(string $keyId): string
    {
        $key = Arr::first($this->keys(), fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? null) === $keyId);

        if ($key === null) {
            Cache::forget(self::CacheKey);
            $key = Arr::first($this->keys(), fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? null) === $keyId);
        }

        if (! is_array($key) || ($key['kty'] ?? null) !== 'RSA') {
            throw ValidationException::withMessages(['id_token' => ['Google returned an unknown signing key.']]);
        }

        $modulus = $this->decode((string) ($key['n'] ?? ''));
        $exponent = $this->decode((string) ($key['e'] ?? ''));

        if ($modulus === '' || $exponent === '') {
            throw ValidationException::withMessages(['id_token' => ['Google returned an invalid signing key.']]);
        }

        return $this->rsaPublicKey($modulus, $exponent);
    }

    /** @return array<int, mixed> */
    private function keys(): array
    {
        return Cache::remember(self::CacheKey, now()->addHour(), function (): array {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->get(self::KeysEndpoint);

            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new RuntimeException('Google signing keys could not be retrieved.');
            }

            return $response->json('keys');
        });
    }

    private function decode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $decoded = base64_decode($value.str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        if ($decoded === false) {
            throw ValidationException::withMessages(['id_token' => ['Google returned an invalid signing key.']]);
        }

        return $decoded;
    }

    private function rsaPublicKey(string $modulus, string $exponent): string
    {
        $rsaPublicKey = $this->derSequence(
            $this->derInteger($modulus).$this->derInteger($exponent),
        );
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');

        if ($algorithmIdentifier === false) {
            throw new RuntimeException('The RSA algorithm identifier is invalid.');
        }

        $subjectPublicKeyInfo = $this->derSequence(
            $algorithmIdentifier.$this->derBitString($rsaPublicKey),
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $value): string
    {
        return "\x30".$this->derLength(strlen($value)).$value;
    }

    private function derBitString(string $value): string
    {
        return "\x03".$this->derLength(strlen($value) + 1)."\x00".$value;
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        $value = $value === '' || ord($value[0]) >= 128 ? "\x00".$value : $value;

        return "\x02".$this->derLength(strlen($value)).$value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(128 | strlen($bytes)).$bytes;
    }
}
