<?php

namespace Tests\Unit\Services\Application\Handlers\User;

use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\User\ConfirmEmailChangeMail;
use HiEvents\Mail\User\EmailChangeRequestedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Application\Handlers\User\UpdateMeHandler;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\PasswordLoginPolicy;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
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

    private SsoTicketService $ticketService;

    private SsoEnforcementService|MockInterface $ssoEnforcementService;

    private UpdateMeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->encryptedPayloadService = Mockery::mock(EncryptedPayloadService::class);

        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->andReturnUsing(fn (string $value, ?string $hash) => $value === 'secret' && $hash === 'hashed');
        $this->ticketService = new SsoTicketService(new Repository(new ArrayStore));
        $this->ssoEnforcementService = Mockery::mock(SsoEnforcementService::class);
        $this->ssoEnforcementService->shouldReceive('assertEmailChangeAllowed')->byDefault();

        $this->handler = new UpdateMeHandler(
            $this->userRepository,
            $hasher,
            Mail::getFacadeRoot(),
            $this->encryptedPayloadService,
            Mockery::mock(TrustedDeviceService::class),
            Mockery::mock(AccountUserRepositoryInterface::class),
            new IdentityConfirmationService($hasher, $this->ticketService, $this->passwordLoginPolicy()),
            $this->ssoEnforcementService,
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

    public function test_refuses_an_email_outside_the_verified_domains_of_an_account_that_requires_single_sign_on(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->userRepository->shouldNotReceive('updateWhere');
        $this->ssoEnforcementService->shouldReceive('assertEmailChangeAllowed')
            ->with(1, 'personal@gmail.com')
            ->andThrow(new AccountSsoConfigurationException('Your organization requires single sign-on.', 'email'));

        $this->expectException(ResourceConflictException::class);

        try {
            $this->handler->handle($this->dto('personal@gmail.com'));
        } finally {
            Mail::assertNothingQueued();
        }
    }

    public function test_changing_only_the_case_of_the_email_is_not_an_email_change(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => ! array_key_exists('pending_email', $attributes)),
            ['id' => 1],
        );

        $this->handler->handle($this->dto(' Old@Example.com', confirmationPassword: null));

        Mail::assertNothingQueued();
    }

    public function test_refuses_an_email_change_without_confirming_the_password(): void
    {
        $this->arrangeUser(emailVerifiedAt: null);
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->expectException(IdentityConfirmationFailedException::class);

        try {
            $this->handler->handle($this->dto('new@example.com', confirmationPassword: 'wrong'));
        } finally {
            Mail::assertNothingQueued();
        }
    }

    public function test_a_user_without_a_password_confirms_an_email_change_with_a_recent_sign_in(): void
    {
        $this->arrangeUser(emailVerifiedAt: null, password: null);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->userRepository->shouldReceive('updateWhere')->once();
        $this->encryptedPayloadService->shouldReceive('encryptPayload')->andReturn('token');
        $proof = $this->ticketService->issue(SsoTicketType::REAUTH, ['user_id' => 1, 'token_id' => 'jti-1']);

        $this->handler->handle($this->dto('new@example.com', confirmationPassword: null, reauthProof: $proof, tokenId: 'jti-1'));

        Mail::assertQueued(ConfirmEmailChangeMail::class);
    }

    public function test_a_recent_sign_in_from_another_session_does_not_confirm_an_email_change(): void
    {
        $this->arrangeUser(emailVerifiedAt: null, password: null);
        $this->userRepository->shouldNotReceive('updateWhere');
        $proof = $this->ticketService->issue(SsoTicketType::REAUTH, ['user_id' => 1, 'token_id' => 'jti-1']);

        $this->expectException(IdentityConfirmationFailedException::class);

        $this->handler->handle($this->dto('new@example.com', confirmationPassword: null, reauthProof: $proof, tokenId: 'jti-2'));
    }

    private function arrangeUser(?string $emailVerifiedAt, ?string $password = 'hashed'): void
    {
        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(1, 9)->andReturn(
            (new UserDomainObject)
                ->setId(1)
                ->setEmail('old@example.com')
                ->setLocale('en')
                ->setPassword($password)
                ->setEmailVerifiedAt($emailVerifiedAt)
        );
    }

    private function dto(
        string $email,
        ?string $confirmationPassword = 'secret',
        ?string $reauthProof = null,
        ?string $tokenId = null,
    ): UpdateMeDTO {
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
            confirmation_password: $confirmationPassword,
            reauth_proof: $reauthProof,
            token_id: $tokenId,
        );
    }

    private function passwordLoginPolicy(): PasswordLoginPolicy
    {
        $policy = Mockery::mock(PasswordLoginPolicy::class);
        $policy->shouldReceive('canConfirmWithPassword')->andReturnUsing(fn (UserDomainObject $user) => $user->getPassword() !== null);

        return $policy;
    }
}
