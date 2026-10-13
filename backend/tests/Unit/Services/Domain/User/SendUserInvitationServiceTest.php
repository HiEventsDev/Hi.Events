<?php

namespace Tests\Unit\Services\Domain\User;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Mail\User\UserInvited;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\User\SendUserInvitationService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SendUserInvitationServiceTest extends TestCase
{
    private AccountRepositoryInterface|MockInterface $accountRepository;

    private SendUserInvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);

        $encryptedPayloadService = Mockery::mock(EncryptedPayloadService::class);
        $encryptedPayloadService->shouldReceive('encryptPayload')->andReturn('token');

        $this->service = new SendUserInvitationService(
            $encryptedPayloadService,
            Mail::getFacadeRoot(),
            new Repository(['app' => ['name' => 'Hi.Events']]),
            $this->accountRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_invitations_from_verified_accounts(): void
    {
        $this->givenAccountVerifiedAt('2026-01-01 00:00:00');

        $this->service->sendInvitation($this->invitedUser(), 9);

        Mail::assertQueued(UserInvited::class, fn (UserInvited $mail) => $mail->hasTo('invitee@example.com'));
    }

    public function test_refuses_to_send_invitations_from_unverified_accounts(): void
    {
        $this->givenAccountVerifiedAt(null);

        $this->expectException(AccountNotVerifiedException::class);

        try {
            $this->service->sendInvitation($this->invitedUser(), 9);
        } finally {
            Mail::assertNothingQueued();
        }
    }

    private function givenAccountVerifiedAt(?string $verifiedAt): void
    {
        $this->accountRepository->shouldReceive('findById')->with(9)->andReturn(
            (new AccountDomainObject)->setId(9)->setAccountVerifiedAt($verifiedAt)
        );
    }

    private function invitedUser(): UserDomainObject
    {
        return (new UserDomainObject)->setId(2)->setEmail('invitee@example.com')->setLocale('en');
    }
}
