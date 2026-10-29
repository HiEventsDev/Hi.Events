<?php

namespace HiEvents\Mail\User;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/user-invited.blade.php
 */
class UserInvited extends BaseMail
{
    private string $appName;

    private string $inviteLink;

    public function __construct(
        string $appName,
        string $inviteLink
    ) {
        parent::__construct();

        $this->appName = $appName;
        $this->inviteLink = $inviteLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'ve been invited to join :appName', ['appName' => $this->appName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.user-invited',
            with: [
                'appName' => $this->appName,
                'inviteLink' => $this->inviteLink,
            ]
        );
    }
}
