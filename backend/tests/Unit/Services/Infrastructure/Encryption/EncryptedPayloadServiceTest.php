<?php

namespace Tests\Unit\Services\Infrastructure\Encryption;

use Carbon\Carbon;
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

    public function testEncryptPayload(): void
    {
        $payload = ['data' => 'test'];
        $encryptedPayload = 'encryptedString';
        $expiry = Carbon::now()->addHours(1);

        $this->encrypter->shouldReceive('encrypt')->once()->andReturn($encryptedPayload);

        $result = $this->service->encryptPayload($payload, $expiry);

        $this->assertEquals($encryptedPayload, $result);
    }

    public function testDecryptPayloadSuccess(): void
    {
        $encryptedPayload = 'encryptedString';
        $decryptedPayload = ['data' => 'test', 'exp' => Carbon::now()->addHours(1)->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')->once()->andReturn($decryptedPayload);

        $result = $this->service->decryptPayload($encryptedPayload);

        $this->assertEquals($decryptedPayload, $result);
    }

    public function testDecryptPayloadExpiredException(): void
    {
        $this->expectException(EncryptedPayloadExpiredException::class);

        $encryptedPayload = 'encryptedString';
        $expiredPayload = ['data' => 'test', 'exp' => Carbon::now()->subMinutes(1)->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')->once()->andReturn($expiredPayload);

        $this->service->decryptPayload($encryptedPayload);
    }

    public function testDecryptPayloadDecryptionFailedException(): void
    {
        $this->expectException(DecryptionFailedException::class);

        $encryptedPayload = 'encryptedString';

        $this->encrypter->shouldReceive('decrypt')->once()->andThrow(new DecryptException());

        $this->service->decryptPayload($encryptedPayload);
    }
}
