<?php

namespace Tests\Unit\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\User\ConfirmEmailChangeMail;
use HiEvents\Mail\User\EmailChangeRequestedMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Application\Handlers\User\UpdateMeHandler;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateMeHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private UserRepositoryInterface|MockInterface $userRepository;

    private EncryptedPayloadService|MockInterface $encryptedPayloadService;

    private UpdateMeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->encryptedPayloadService = Mockery::mock(EncryptedPayloadService::class);

        $this->handler = new UpdateMeHandler(
            $this->userRepository,
            Mockery::mock(Hasher::class),
            Mail::getFacadeRoot(),
            $this->encryptedPayloadService,
            Mockery::mock(TrustedDeviceService::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_the_email_change_confirmation_to_the_new_address(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->userRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['pending_email'] === 'new@example.com'),
            ['id' => 1],
        );
        $this->encryptedPayloadService
            ->shouldReceive('encryptPayload')
            ->once()
            ->with(['id' => 1, 'email' => 'new@example.com'])
            ->andReturn('token');

        $this->handler->handle($this->dto('new@example.com'));

        Mail::assertQueued(ConfirmEmailChangeMail::class, fn (ConfirmEmailChangeMail $mail) => $mail->hasTo('new@example.com')
            && ! $mail->hasTo('old@example.com'));
        Mail::assertNotQueued(EmailChangeRequestedMail::class);
    }

    public function test_alerts_the_verified_current_address_that_a_change_was_requested(): void
    {
        $this->arrangeUser(emailVerifiedAt: '2026-01-01 00:00:00');
        $this->userRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->userRepository->shouldReceive('updateWhere')->once();
        $this->encryptedPayloadService->shouldReceive('encryptPayload')->andReturn('token');

        $this->handler->handle($this->dto('new@example.com'));

        Mail::assertQueued(EmailChangeRequestedMail::class, fn (EmailChangeRequestedMail $mail) => $mail->hasTo('old@example.com')
            && ! $mail->hasTo('new@example.com'));
    }

    public function test_escapes_like_wildcards_when_checking_the_email_is_available(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ['email', 'ilike', 'john\\_doe\\%@example.com'],
                ['id', '!=', 1],
            ])
            ->andReturnNull();
        $this->userRepository->shouldReceive('updateWhere')->once();
        $this->encryptedPayloadService->shouldReceive('encryptPayload')->andReturn('token');

        $this->handler->handle($this->dto('john_doe%@example.com'));
    }

    public function test_refuses_an_email_address_another_user_already_has(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturn((new UserDomainObject)->setId(2));
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        try {
            $this->handler->handle($this->dto('Taken@Example.com'));
        } finally {
            Mail::assertNothingQueued();
        }
    }

    private function arrangeUser(?string $emailVerifiedAt): void
    {
        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(1, 9)->andReturn(
            (new UserDomainObject)
                ->setId(1)
                ->setEmail('old@example.com')
                ->setLocale('en')
                ->setEmailVerifiedAt($emailVerifiedAt)
        );
    }

    private function dto(string $email): UpdateMeDTO
    {
        return new UpdateMeDTO(
            id: 1,
            account_id: 9,
            first_name: 'Dave',
            last_name: 'Earley',
            email: $email,
            timezone: 'UTC',
            password: null,
            current_password: null,
            locale: 'en',
        );
    }
}
