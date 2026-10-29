<?php

namespace Tests\Unit\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Application\Handlers\User\UpdateMeHandler;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateMeHandlerPasswordChangeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private UserRepositoryInterface|MockInterface $userRepository;

    private TrustedDeviceService|MockInterface $trustedDeviceService;

    private UpdateMeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->trustedDeviceService = Mockery::mock(TrustedDeviceService::class);

        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->andReturnUsing(fn (string $value, ?string $hash) => $value === 'current-secret' && $hash === 'hashed');
        $hasher->shouldReceive('make')->with('new-secret')->andReturn('new-hash');

        $this->handler = new UpdateMeHandler(
            $this->userRepository,
            $hasher,
            Mail::getFacadeRoot(),
            Mockery::mock(EncryptedPayloadService::class),
            $this->trustedDeviceService,
            Mockery::mock(AccountUserRepositoryInterface::class),
            Mockery::mock(IdentityConfirmationService::class),
            Mockery::mock(SsoEnforcementService::class),
        );
    }

    public function test_a_password_change_sent_with_profile_details_saves_both(): void
    {
        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(1, 9)->andReturn(
            (new UserDomainObject)
                ->setId(1)
                ->setEmail('dave@example.com')
                ->setLocale('en')
                ->setPassword('hashed')
        );
        $this->trustedDeviceService->shouldReceive('revokeAll')->once()->with(1);
        $this->userRepository->shouldReceive('updateWhere')->once()->with(
            [
                'password' => 'new-hash',
                'first_name' => 'Dave',
                'last_name' => 'Earley',
                'timezone' => 'UTC',
                'locale' => 'en',
            ],
            ['id' => 1],
        );

        $this->handler->handle(new UpdateMeDTO(
            id: 1,
            account_id: 9,
            first_name: 'Dave',
            last_name: 'Earley',
            email: 'dave@example.com',
            timezone: 'UTC',
            password: 'new-secret',
            current_password: 'current-secret',
            locale: 'en',
        ));
    }
}
