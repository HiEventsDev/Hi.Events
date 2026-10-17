<?php

namespace HiEvents\Services\Domain\Auth\Sso\Jwk;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Exceptions\SsoException;

class JwkConverter
{
    private const RSA_ENCRYPTION_OID = '1.2.840.113549.1.1.1';

    private const EC_PUBLIC_KEY_OID = '1.2.840.10045.2.1';

    private const CURVE_OIDS = [
        'P-256' => '1.2.840.10045.3.1.7',
        'P-384' => '1.3.132.0.34',
    ];

    private const CURVE_COORDINATE_LENGTHS = [
        'P-256' => 32,
        'P-384' => 48,
    ];

    /**
     * @throws SsoException
     */
    public function toPem(array $jwk): string
    {
        $der = match ($jwk['kty'] ?? null) {
            'RSA' => $this->rsaToDer($jwk),
            'EC' => $this->ecToDer($jwk),
            default => throw $this->invalidKey(),
        };

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    /**
     * @throws SsoException
     */
    private function rsaToDer(array $jwk): string
    {
        $modulus = $this->decode($jwk['n'] ?? null);
        $exponent = $this->decode($jwk['e'] ?? null);

        $publicKey = $this->sequence($this->integer($modulus).$this->integer($exponent));

        return $this->sequence(
            $this->sequence($this->oid(self::RSA_ENCRYPTION_OID)."\x05\x00")
            .$this->bitString($publicKey)
        );
    }

    /**
     * @throws SsoException
     */
    private function ecToDer(array $jwk): string
    {
        $curve = $jwk['crv'] ?? null;

        if (! isset(self::CURVE_OIDS[$curve])) {
            throw $this->invalidKey();
        }

        $x = $this->decode($jwk['x'] ?? null);
        $y = $this->decode($jwk['y'] ?? null);
        $length = self::CURVE_COORDINATE_LENGTHS[$curve];

        if (strlen($x) !== $length || strlen($y) !== $length) {
            throw $this->invalidKey();
        }

        return $this->sequence(
            $this->sequence($this->oid(self::EC_PUBLIC_KEY_OID).$this->oid(self::CURVE_OIDS[$curve]))
            .$this->bitString("\x04".$x.$y)
        );
    }

    /**
     * @throws SsoException
     */
    private function decode(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw $this->invalidKey();
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false || $decoded === '') {
            throw $this->invalidKey();
        }

        return $decoded;
    }

    private function sequence(string $contents): string
    {
        return "\x30".$this->length(strlen($contents)).$contents;
    }

    private function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '' || ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->length(strlen($bytes)).$bytes;
    }

    private function bitString(string $bytes): string
    {
        return "\x03".$this->length(strlen($bytes) + 1)."\x00".$bytes;
    }

    private function oid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $encoded = chr($parts[0] * 40 + $parts[1]);

        foreach (array_slice($parts, 2) as $part) {
            $chunk = chr($part & 0x7F);
            $part >>= 7;

            while ($part > 0) {
                $chunk = chr(($part & 0x7F) | 0x80).$chunk;
                $part >>= 7;
            }

            $encoded .= $chunk;
        }

        return "\x06".$this->length(strlen($encoded)).$encoded;
    }

    private function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function invalidKey(): SsoException
    {
        return new SsoException(SsoErrorCode::TOKEN_INVALID, 'Unsupported or malformed signing key');
    }
}
