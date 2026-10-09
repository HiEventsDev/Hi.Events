<?php

namespace HiEvents\Mail\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/two-factor-recovery-code-used.blade.php
 */
class TwoFactorRecoveryCodeUsedMail extends BaseMail
{
    public function __construct(
        private readonly UserDomainObject $user,
        private readonly int $recoveryCodesRemaining,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A recovery code was used to sign in'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.two-factor-recovery-code-used',
            with: [
                'user' => $this->user,
                'recoveryCodesRemaining' => $this->recoveryCodesRemaining,
                'securityUrl' => Url::getFrontEndUrlFromConfig(Url::PROFILE_SECURITY),
            ]
        );
    }
}
