<?php

namespace Tests\Unit\Service\Common;

use Carbon\Carbon;
use HiEvents\Services\Infrastructure\Encyption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encyption\Exception\DecryptionFailedException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class EncryptedPayloadServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected MockInterface|Encrypter $encrypter;

    protected EncryptedPayloadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encrypter = Mockery::mock(Encrypter::class);
        $this->service = new EncryptedPayloadService($this->encrypter);
    }

    public function testEncryptPayload(): void
    {
        $payload = ['data' => 'value'];
        $encryptedPayload = 'encrypted_string';

        $this->encrypter->shouldReceive('encrypt')
            ->once()
            ->with(Mockery::on(static function ($arg) {
                return isset($arg['exp'], $arg['data']);
            }))
            ->andReturn($encryptedPayload);

        $result = $this->service->encryptPayload($payload);

        $this->assertEquals($encryptedPayload, $result);
    }

    public function testDecryptPayload(): void
    {
        $encryptedPayload = 'encrypted_string';
        $expectedDecrypted = ['data' => 'value', 'exp' => Carbon::now()->addHours(48)->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')
            ->once()
            ->with($encryptedPayload)
            ->andReturn($expectedDecrypted);

        $result = $this->service->decryptPayload($encryptedPayload);

        $this->assertEquals($expectedDecrypted, $result);
    }

    public function testDecryptPayloadWithExpiredTime(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Payload has expired or is invalid.');

        $expiredPayload = 'expired_encrypted_string';
        $expectedDecrypted = ['data' => 'value', 'exp' => Carbon::now()->subHours()->toIso8601String()];

        $this->encrypter->shouldReceive('decrypt')
            ->once()
            ->with($expiredPayload)
            ->andReturn($expectedDecrypted);

        $this->service->decryptPayload($expiredPayload);
    }

    public function testDecryptPayloadThrowsException(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Payload could not be decrypted.');

        $badPayload = 'bad_encrypted_string';

        $this->encrypter->shouldReceive('decrypt')
            ->once()
            ->with($badPayload)
            ->andThrow(new DecryptException());

        $this->service->decryptPayload($badPayload);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
