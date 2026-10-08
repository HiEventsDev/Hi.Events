<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Licensing;

use HiEvents\Enterprise\Licensing\TrustedKeys;
use Tests\TestCase;

class TrustedKeysTest extends TestCase
{
    public function test_every_trusted_key_is_an_ed25519_public_key(): void
    {
        $this->assertNotEmpty(TrustedKeys::KEYS);

        foreach (TrustedKeys::KEYS as $kid => $publicKey) {
            $decoded = base64_decode($publicKey, true);

            $this->assertNotFalse($decoded, "Trusted key [$kid] is not base64");
            $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($decoded), "Trusted key [$kid] is not an Ed25519 public key");
        }
    }
}
