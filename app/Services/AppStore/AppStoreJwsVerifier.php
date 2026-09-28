<?php

namespace App\Services\AppStore;

use Illuminate\Validation\ValidationException;

class AppStoreJwsVerifier
{
    /**
     * Verify a JWS signed by the App Store and return its decoded payload.
     *
     * @return array<string, mixed>
     */
    public function verify(string $signedPayload): array
    {
        $parts = explode('.', $signedPayload);
        if (count($parts) !== 3) {
            $this->invalid();
        }

        [$header, $payload, $signature] = $parts;
        $headerData = $this->decodeJson($header);
        $claims = $this->decodeJson($payload);
        $certificateChain = $headerData['x5c'] ?? null;

        if (($headerData['alg'] ?? null) !== 'ES256' || ! is_array($certificateChain) || count($certificateChain) < 3) {
            $this->invalid();
        }

        $certificates = array_map(function (mixed $certificate): string {
            if (! is_string($certificate)) {
                $this->invalid();
            }

            return $this->certificatePem($certificate);
        }, $certificateChain);

        $this->verifyCertificateChain($certificates);

        $verified = openssl_verify(
            $header.'.'.$payload,
            $this->ecdsaSignatureToDer($this->decode($signature)),
            $certificates[0],
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            $this->invalid();
        }

        return $claims;
    }

    /** @param list<string> $certificates */
    private function verifyCertificateChain(array $certificates): void
    {
        foreach ($certificates as $certificate) {
            $details = openssl_x509_parse($certificate);
            if (! is_array($details) || ! isset($details['validFrom_time_t'], $details['validTo_time_t'])
                || $details['validFrom_time_t'] > now()->getTimestamp()
                || $details['validTo_time_t'] < now()->getTimestamp()) {
                $this->invalid();
            }
        }

        foreach (array_keys($certificates) as $index) {
            if ($index === array_key_last($certificates)) {
                break;
            }

            $issuerKey = openssl_pkey_get_public($certificates[$index + 1]);
            if ($issuerKey === false || openssl_x509_verify($certificates[$index], $issuerKey) !== 1) {
                $this->invalid();
            }
        }

        $root = $certificates[array_key_last($certificates)];
        $fingerprint = openssl_x509_fingerprint($root, 'sha256');
        $trustedFingerprints = config('services.app_store.root_certificate_fingerprints', []);

        if (! is_string($fingerprint) || ! in_array(strtolower($fingerprint), $trustedFingerprints, true)) {
            $this->invalid();
        }
    }

    private function certificatePem(string $encodedCertificate): string
    {
        $certificate = base64_decode($encodedCertificate, true);
        if ($certificate === false) {
            $this->invalid();
        }

        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode($certificate), 64, "\n")
            ."-----END CERTIFICATE-----\n";
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

    private function ecdsaSignatureToDer(string $signature): string
    {
        if (strlen($signature) !== 64) {
            $this->invalid();
        }

        $r = $this->derInteger(substr($signature, 0, 32));
        $s = $this->derInteger(substr($signature, 32, 32));
        $value = $r.$s;

        return "\x30".$this->derLength(strlen($value)).$value;
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

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'signed_transaction' => ['The App Store transaction could not be verified.'],
        ]);
    }
}
