<?php

namespace App\Services\Apple\Auth;

use RuntimeException;

class AppleClientSecret
{
    public function clientId(): string
    {
        return $this->config('client_id');
    }

    public function webClientId(): string
    {
        return $this->config('web_client_id');
    }

    public function create(string $clientId): string
    {
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", $this->config('private_key')));

        if ($key === false) {
            throw new RuntimeException('The Apple private key is invalid.');
        }

        $issuedAt = now()->getTimestamp();
        $header = $this->encode(['alg' => 'ES256', 'kid' => $this->config('key_id')]);
        $payload = $this->encode([
            'iss' => $this->config('team_id'), 'iat' => $issuedAt, 'exp' => $issuedAt + 15_552_000,
            'aud' => 'https://appleid.apple.com', 'sub' => $clientId,
        ]);
        $signed = openssl_sign($header.'.'.$payload, $signature, $key, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('The Apple client secret could not be signed.');
        }

        return $header.'.'.$payload.'.'.$this->encode($this->derToJose($signature), false);
    }

    private function config(string $key): string
    {
        $value = config('services.apple.'.$key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("The Apple {$key} configuration is missing.");
        }

        return $value;
    }

    /** @param array<string, int|string> $value */
    private function encode(array|string $value, bool $json = true): string
    {
        $value = $json ? json_encode($value, JSON_THROW_ON_ERROR) : $value;

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function derToJose(string $signature): string
    {
        $offset = ord($signature[1]) > 127 ? 3 : 2;
        $offset++;
        $rLength = ord($signature[$offset++]);
        $r = substr($signature, $offset, $rLength);
        $offset += $rLength + 1;
        $sLength = ord($signature[$offset++]);
        $s = substr($signature, $offset, $sLength);

        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT).str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }
}
