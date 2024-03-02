<?php

namespace HiEvents\Services\Common\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\UserInvited;
use HiEvents\Services\Common\EncryptedPayloadService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;

class SendUserInvitationService
{
    private EncryptedPayloadService $encryptedPayloadService;

    private Mailer $mailer;

    private Repository $config;

    public function __construct(
        EncryptedPayloadService $encryptedPayloadService,
        Mailer                  $mailer,
        Repository              $config,
    )
    {
        $this->encryptedPayloadService = $encryptedPayloadService;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    public function sendInvitation(UserDomainObject $invitedUser): void
    {
        $invitedPayload = $this->encryptedPayloadService->encryptPayload(
            payload: [
                'user_id' => $invitedUser->getId(),
                'email' => $invitedUser->getEmail(),
            ],
            expiry: now()->addWeek(),
        );

        $this->mailer->to($invitedUser->getEmail())->send(new UserInvited(
            invitedUser: $invitedUser,
            appName: $this->config->get('app.name'),
            inviteLink: sprintf(Url::getFrontEndUrlFromConfig(Url::ACCEPT_INVITATION), $invitedPayload),
        ));
    }
}
