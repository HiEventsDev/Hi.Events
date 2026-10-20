<?php

namespace HiEvents\Services\Domain\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Helper\Url;
use HiEvents\Mail\User\UserInvited;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;

class SendUserInvitationService
{
    private EncryptedPayloadService $encryptedPayloadService;

    private Mailer $mailer;

    private Repository $config;

    private AccountRepositoryInterface $accountRepository;

    public function __construct(
        EncryptedPayloadService $encryptedPayloadService,
        Mailer $mailer,
        Repository $config,
        AccountRepositoryInterface $accountRepository,
    ) {
        $this->encryptedPayloadService = $encryptedPayloadService;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->accountRepository = $accountRepository;
    }

    /**
     * @throws AccountNotVerifiedException
     */
    public function sendInvitation(UserDomainObject $invitedUser, int $accountId): void
    {
        if ($this->accountRepository->findById($accountId)->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(
                __('Please verify your email address before inviting team members.')
            );
        }

        $invitedPayload = $this->encryptedPayloadService->encryptPayload(
            payload: [
                'user_id' => $invitedUser->getId(),
                'account_id' => $accountId,
            ],
            expiry: now()->addWeek(),
        );

        $this->mailer
            ->to($invitedUser->getEmail())
            ->locale($invitedUser->getLocale())
            ->send(new UserInvited(
                appName: $this->config->get('app.name'),
                inviteLink: sprintf(Url::getFrontEndUrlFromConfig(Url::ACCEPT_INVITATION), $invitedPayload),
            ));
    }
}
