<?php

namespace Tests\Unit\Services\Infrastructure\Encryption;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EncryptedPayloadPurpose;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Mockery as m;
use Tests\TestCase;

class EncryptedPayloadServiceTest extends TestCase
{
    private Encrypter $encrypter;

    private EncryptedPayloadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encrypter = m::mock(Encrypter::class);
        $this->service = new EncryptedPayloadService($this->encrypter);
    }

    public function test_encrypt_payload(): void
    {
        $payload = ['data' => 'test'];
        $encryptedPayload = 'encryptedString';
        $expiry = Carbon::now()->addHours(1);

        $this->encrypter->shouldReceive('encrypt')->once()
            ->with(m::on(fn (array $sealed) => $sealed['purpose'] === 'EMAIL_CHANGE' && $sealed['data'] === 'test'))
            ->andReturn($encryptedPayload);

        $result = $this->service->encryptPayload($payload, EncryptedPayloadPurpose::EMAIL_CHANGE, $expiry);

        $this->assertEquals($encryptedPayload, $result);
    }

    public function test_decrypt_payload_success(): void
    {
        $encryptedPayload = 'encryptedString';
        $decryptedPayload = ['data' => 'test', 'purpose' => 'INVITATION', 'exp' => Carbon::now()->addHours(1)->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')->once()->andReturn($decryptedPayload);

        $result = $this->service->decryptPayload($encryptedPayload, EncryptedPayloadPurpose::INVITATION);

        $this->assertEquals($decryptedPayload, $result);
    }

    public function test_decrypt_payload_expired_exception(): void
    {
        $this->expectException(EncryptedPayloadExpiredException::class);

        $encryptedPayload = 'encryptedString';
        $expiredPayload = ['data' => 'test', 'purpose' => 'INVITATION', 'exp' => Carbon::now()->subMinutes(1)->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')->once()->andReturn($expiredPayload);

        $this->service->decryptPayload($encryptedPayload, EncryptedPayloadPurpose::INVITATION);
    }

    public function test_decrypt_payload_decryption_failed_exception(): void
    {
        $this->expectException(DecryptionFailedException::class);

        $encryptedPayload = 'encryptedString';

        $this->encrypter->shouldReceive('decrypt')->once()->andThrow(new DecryptException);

        $this->service->decryptPayload($encryptedPayload, EncryptedPayloadPurpose::INVITATION);
    }

    public function test_decrypt_payload_rejects_a_payload_issued_for_another_purpose(): void
    {
        $this->expectException(DecryptionFailedException::class);

        $this->encrypter->shouldReceive('decrypt')->once()->andReturn([
            'id' => 1,
            'email' => 'new@example.com',
            'purpose' => 'EMAIL_CHANGE',
            'exp' => Carbon::now()->addHours(1)->toIso8601String(),
        ]);

        $this->service->decryptPayload('encryptedString', EncryptedPayloadPurpose::EMAIL_CONFIRMATION);
    }

    public function test_decrypt_payload_reads_the_purpose_of_a_payload_issued_before_purposes_existed(): void
    {
        $exp = Carbon::now()->addHours(1)->toIso8601String();
        $this->encrypter->shouldReceive('decrypt')->andReturn(
            ['id' => 1, 'email' => 'new@example.com', 'exp' => $exp],
            ['id' => 1, 'exp' => $exp],
            ['user_id' => 1, 'account_id' => 2, 'exp' => $exp],
            ['id' => 1, 'email' => 'new@example.com', 'exp' => $exp],
        );

        $this->assertSame(1, $this->service->decryptPayload('a', EncryptedPayloadPurpose::EMAIL_CHANGE)['id']);
        $this->assertSame(1, $this->service->decryptPayload('b', EncryptedPayloadPurpose::EMAIL_CONFIRMATION)['id']);
        $this->assertSame(2, $this->service->decryptPayload('c', EncryptedPayloadPurpose::INVITATION)['account_id']);

        $this->expectException(DecryptionFailedException::class);
        $this->service->decryptPayload('d', EncryptedPayloadPurpose::EMAIL_CONFIRMATION);
    }
}
