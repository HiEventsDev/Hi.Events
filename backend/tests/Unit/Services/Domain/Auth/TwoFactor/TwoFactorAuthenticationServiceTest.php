<?php

namespace Tests\Unit\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use Illuminate\Config\Repository as Config;
use Illuminate\Encryption\Encrypter;
use Mockery as m;
use PragmaRX\Google2FA\Google2FA;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class TwoFactorAuthenticationServiceTest extends TestCase
{
    private Google2FA $google2fa;

    private Encrypter $encrypter;

    private UserRepositoryInterface $userRepository;

    private LoggerInterface $logger;

    private TwoFactorAuthenticationService $service;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA;
        $this->encrypter = new Encrypter(str_repeat('k', 32), 'AES-256-CBC');
        $this->userRepository = m::mock(UserRepositoryInterface::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->secret = $this->google2fa->generateSecretKey(32);

        $this->service = new TwoFactorAuthenticationService(
            $this->google2fa,
            $this->encrypter,
            $this->userRepository,
            new Config(['app' => ['name' => 'Hi.Events']]),
            $this->logger,
        );
    }

    public function test_begin_setup_stores_an_encrypted_secret_and_returns_an_otpauth_uri(): void
    {
        $user = (new UserDomainObject)->setId(4)->setEmail('organizer@example.com');
        $storedSecret = null;

        $this->userRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes) use (&$storedSecret) {
                $storedSecret = $attributes['two_factor_secret'];

                return $attributes['two_factor_confirmed_at'] === null
                    && $attributes['two_factor_recovery_codes'] === null;
            })
            ->andReturn(1);

        $setup = $this->service->beginSetup($user);

        $this->assertNotSame($setup->secret, $storedSecret);
        $this->assertSame($setup->secret, $this->encrypter->decryptString($storedSecret));
        $this->assertStringStartsWith('otpauth://totp/Hi.Events:organizer%40example.com?secret='.$setup->secret, $setup->otpauthUri);
    }

    public function test_begin_setup_refuses_when_already_enabled(): void
    {
        $this->expectException(ResourceConflictException::class);

        $this->service->beginSetup($this->enabledUser());
    }

    public function test_verify_code_accepts_the_current_code_and_records_its_timestep(): void
    {
        $this->userRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(fn (array $attributes) => $attributes['two_factor_last_used_timestep'] === $this->google2fa->getTimestamp())
            ->andReturn(1);

        $this->assertTrue($this->service->verifyCode($this->enabledUser(), $this->google2fa->getCurrentOtp($this->secret)));
    }

    public function test_verify_code_tolerates_spaces_in_the_code(): void
    {
        $this->userRepository->shouldReceive('updateWhere')->once()->andReturn(1);
        $code = $this->google2fa->getCurrentOtp($this->secret);

        $this->assertTrue($this->service->verifyCode($this->enabledUser(), substr($code, 0, 3).' '.substr($code, 3)));
    }

    public function test_verify_code_rejects_a_code_from_an_already_used_timestep(): void
    {
        $user = $this->enabledUser()->setTwoFactorLastUsedTimestep($this->google2fa->getTimestamp() + 1);

        $this->userRepository->shouldNotReceive('updateWhere');

        $this->assertFalse($this->service->verifyCode($user, $this->google2fa->getCurrentOtp($this->secret)));
    }

    public function test_verify_code_fails_when_a_concurrent_request_claimed_the_timestep(): void
    {
        $this->userRepository->shouldReceive('updateWhere')->once()->andReturn(0);

        $this->assertFalse($this->service->verifyCode($this->enabledUser(), $this->google2fa->getCurrentOtp($this->secret)));
    }

    public function test_verify_code_rejects_malformed_codes(): void
    {
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->assertFalse($this->service->verifyCode($this->enabledUser(), '12345'));
        $this->assertFalse($this->service->verifyCode($this->enabledUser(), 'abcdef'));
    }

    public function test_verify_code_fails_safely_when_the_secret_cannot_be_decrypted(): void
    {
        $user = $this->enabledUser()->setTwoFactorSecret('not-encrypted');

        $this->logger->shouldReceive('error')->once();
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->assertFalse($this->service->verifyCode($user, $this->google2fa->getCurrentOtp($this->secret)));
    }

    public function test_is_enabled_requires_a_confirmed_secret(): void
    {
        $this->assertTrue($this->service->isEnabled($this->enabledUser()));
        $this->assertFalse($this->service->isEnabled($this->enabledUser()->setTwoFactorConfirmedAt(null)));
        $this->assertTrue($this->service->hasPendingSetup($this->enabledUser()->setTwoFactorConfirmedAt(null)));
    }

    private function enabledUser(): UserDomainObject
    {
        return (new UserDomainObject)
            ->setId(4)
            ->setEmail('organizer@example.com')
            ->setTwoFactorSecret($this->encrypter->encryptString($this->secret))
            ->setTwoFactorConfirmedAt(now()->toDateTimeString());
    }
}
