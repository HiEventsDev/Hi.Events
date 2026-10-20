<?php

namespace Tests\Unit\Services\Domain\Auth\Sso;

use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\Jwk\JwkConverter;
use Tests\TestCase;

class JwkConverterTest extends TestCase
{
    public function test_converts_an_rsa_key_to_the_same_pem_as_openssl(): void
    {
        $details = openssl_pkey_get_details(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]));

        $pem = (new JwkConverter)->toPem([
            'kty' => 'RSA',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]);

        $this->assertSame($details['key'], $pem);
    }

    public function test_converts_an_ec_key_to_the_same_pem_as_openssl(): void
    {
        $details = openssl_pkey_get_details(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']));

        $pem = (new JwkConverter)->toPem([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $this->base64Url(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            'y' => $this->base64Url(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        ]);

        $this->assertSame($details['key'], $pem);
    }

    public function test_rejects_symmetric_keys(): void
    {
        $this->expectException(SsoException::class);

        (new JwkConverter)->toPem(['kty' => 'oct', 'k' => 'c2VjcmV0']);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
