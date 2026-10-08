<?php

namespace Tests\Unit\Enterprise\Licensing;

use HiEvents\Enterprise\Licensing\Exceptions\InvalidLicenceKeyException;
use HiEvents\Enterprise\Licensing\LicenceKeyCodec;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\Plan;
use Tests\TestCase;

class LicenceKeyCodecTest extends TestCase
{
    private string $secretKey;

    private LicenceKeyCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $this->codec = new LicenceKeyCodec(['test-a' => base64_encode(sodium_crypto_sign_publickey($keypair))]);
    }

    public function test_a_signed_key_decodes_to_its_plan_features_and_dates(): void
    {
        $licence = $this->codec->decode($this->issue());

        $this->assertSame('lic_test', $licence->lid);
        $this->assertSame('Riverside Theatre', $licence->customer);
        $this->assertSame(Plan::ENTERPRISE, $licence->plan);
        $this->assertSame([LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE], $licence->features);
        $this->assertSame('2026-10-01', $licence->issued_at->toDateString());
        $this->assertSame('2027-10-01', $licence->expires_at->toDateString());
    }

    public function test_add_ons_extend_the_plan_and_unknown_or_duplicate_features_are_ignored(): void
    {
        $licence = $this->codec->decode($this->issue(['add' => ['white_label', 'seating', 'hologram_ushers', 42]]));

        $this->assertSame(
            [LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE, LicensedFeature::WHITE_LABEL],
            $licence->features,
        );
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        [$prefix, , $signature] = explode('.', $this->issue());
        $forged = sodium_bin2base64(json_encode($this->payload(['expires_at' => '2099-01-01'])), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        $this->expectInvalid("$prefix.$forged.$signature", 'The licence key signature is invalid');
    }

    public function test_a_key_signed_by_another_private_key_is_rejected(): void
    {
        $otherSecret = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));

        $this->expectInvalid($this->codec->encode($this->payload(), $otherSecret), 'The licence key signature is invalid');
    }

    public function test_an_unknown_key_id_is_rejected(): void
    {
        $this->expectInvalid($this->issue(['kid' => 'test-b']), 'unknown key');
    }

    public function test_an_unknown_plan_is_rejected(): void
    {
        $this->expectInvalid($this->issue(['plan' => 'galactic']), 'unknown plan');
    }

    public function test_missing_fields_and_bad_dates_are_rejected(): void
    {
        $this->expectInvalid($this->issue(['customer' => '']), 'missing customer');
        $this->expectInvalid($this->issue(['expires_at' => 'next tuesday-ish']), 'invalid date');
    }

    public function test_malformed_keys_are_rejected(): void
    {
        foreach (['', 'nonsense', 'hiev1.abc', 'hiev2.a.b', 'hiev1.!!!.abc', 'hiev1.'.base64_encode('"string"').'.abc'] as $key) {
            $this->expectInvalid($key, '');
        }
    }

    private function expectInvalid(string $key, string $reasonContains): void
    {
        try {
            $this->codec->decode($key);
            $this->fail("Expected [$key] to be rejected");
        } catch (InvalidLicenceKeyException $exception) {
            $this->assertStringContainsString($reasonContains, $exception->getMessage());
        }
    }

    private function issue(array $overrides = []): string
    {
        return $this->codec->encode($this->payload($overrides), $this->secretKey);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'kid' => 'test-a',
            'lid' => 'lic_test',
            'customer' => 'Riverside Theatre',
            'issued_at' => '2026-10-01',
            'expires_at' => '2027-10-01',
            'plan' => 'enterprise',
            'add' => [],
            ...$overrides,
        ];
    }
}
