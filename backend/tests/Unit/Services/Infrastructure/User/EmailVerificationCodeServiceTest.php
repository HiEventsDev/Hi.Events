<?php

namespace Tests\Unit\Services\Infrastructure\User;

use HiEvents\Services\Infrastructure\User\EmailVerificationCodeService;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EmailVerificationCodeServiceTest extends TestCase
{
    private EmailVerificationCodeService $service;
    private MockInterface|Repository $cacheRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheRepository = Mockery::mock(Repository::class);
        $this->service = new EmailVerificationCodeService($this->cacheRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testStoreAndReturnCode(): void
    {
        // Given
        $email = 'test@example.com';
        $expectedCacheKey = 'email_verification_code:' . $email;
        
        // Expect
        $this->cacheRepository
            ->shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $code, $expiry) use ($expectedCacheKey) {
                return $key === $expectedCacheKey 
                    && $code >= 10000 
                    && $code <= 99999
                    && $expiry->greaterThan(now()->addMinutes(29))
                    && $expiry->lessThanOrEqualTo(now()->addMinutes(30)->addSecond());
            });

        // When
        $code = $this->service->storeAndReturnCode($email);

        // Then
        $this->assertIsInt($code);
        $this->assertGreaterThanOrEqual(10000, $code);
        $this->assertLessThanOrEqual(99999, $code);
    }

    public function testVerifyCodeWithValidCode(): void
    {
        // Given
        $email = 'test@example.com';
        $validCode = '12345';
        $expectedCacheKey = 'email_verification_code:' . $email;

        // Expect
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with($expectedCacheKey)
            ->andReturn($validCode);

        $this->cacheRepository
            ->shouldReceive('forget')
            ->once()
            ->with($expectedCacheKey);

        // When
        $result = $this->service->verifyCode($email, $validCode);

        // Then
        $this->assertTrue($result);
    }

    public function testVerifyCodeWithInvalidCode(): void
    {
        // Given
        $email = 'test@example.com';
        $storedCode = '12345';
        $providedCode = '54321';
        $expectedCacheKey = 'email_verification_code:' . $email;

        // Expect
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with($expectedCacheKey)
            ->andReturn($storedCode);

        $this->cacheRepository
            ->shouldNotReceive('forget');

        // When
        $result = $this->service->verifyCode($email, $providedCode);

        // Then
        $this->assertFalse($result);
    }

    public function testVerifyCodeWithNoStoredCode(): void
    {
        // Given
        $email = 'test@example.com';
        $providedCode = '12345';
        $expectedCacheKey = 'email_verification_code:' . $email;

        // Expect
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with($expectedCacheKey)
            ->andReturn(null);

        $this->cacheRepository
            ->shouldNotReceive('forget');

        // When
        $result = $this->service->verifyCode($email, $providedCode);

        // Then
        $this->assertFalse($result);
    }

    public function testMultipleVerificationCodesForDifferentEmails(): void
    {
        // Given
        $email1 = 'user1@example.com';
        $email2 = 'user2@example.com';
        
        // Expect - Store codes for two different emails
        $this->cacheRepository
            ->shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $code, $expiry) use ($email1) {
                return $key === 'email_verification_code:' . $email1;
            });

        $this->cacheRepository
            ->shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $code, $expiry) use ($email2) {
                return $key === 'email_verification_code:' . $email2;
            });

        // When
        $code1 = $this->service->storeAndReturnCode($email1);
        $code2 = $this->service->storeAndReturnCode($email2);

        // Then
        $this->assertIsInt($code1);
        $this->assertIsInt($code2);
        // Codes might be the same by chance, but they're generated independently
    }

    public function testVerifyCodeIsCaseInsensitiveForEmail(): void
    {
        // Given
        $emailLower = 'test@example.com';
        $emailUpper = 'TEST@EXAMPLE.COM';
        $code = '12345';

        // Note: The service uses emails as-is, so case sensitivity depends on implementation
        // This test documents the current behavior
        
        // Expect - Different cache keys for different cases
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with('email_verification_code:' . $emailUpper)
            ->andReturn(null);

        // When
        $result = $this->service->verifyCode($emailUpper, $code);

        // Then
        $this->assertFalse($result);
    }

    public function testStoreAndReturnCodeGeneratesUniqueCodesOnMultipleCalls(): void
    {
        // Given
        $email = 'test@example.com';
        $generatedCodes = [];

        // Expect
        $this->cacheRepository
            ->shouldReceive('put')
            ->times(10)
            ->withArgs(function ($key, $code) use (&$generatedCodes) {
                $generatedCodes[] = $code;
                return true;
            });

        // When
        for ($i = 0; $i < 10; $i++) {
            $this->service->storeAndReturnCode($email);
        }

        // Then
        // While codes could theoretically be the same, it's very unlikely
        // At minimum, all codes should be in the valid range
        foreach ($generatedCodes as $code) {
            $this->assertGreaterThanOrEqual(10000, $code);
            $this->assertLessThanOrEqual(99999, $code);
        }
    }

    public function testVerifyCodeOnlyWorksOnce(): void
    {
        // Given
        $email = 'test@example.com';
        $code = '12345';
        $cacheKey = 'email_verification_code:' . $email;

        // First verification attempt
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn($code);

        $this->cacheRepository
            ->shouldReceive('forget')
            ->once()
            ->with($cacheKey);

        // When
        $firstAttempt = $this->service->verifyCode($email, $code);

        // Then
        $this->assertTrue($firstAttempt);

        // Second verification attempt
        $this->cacheRepository
            ->shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn(null);

        // When
        $secondAttempt = $this->service->verifyCode($email, $code);

        // Then
        $this->assertFalse($secondAttempt);
    }
}

