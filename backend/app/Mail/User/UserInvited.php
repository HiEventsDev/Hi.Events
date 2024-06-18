<?php

namespace HiEvents\Mail\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/user-invited.blade.php
 */
class UserInvited extends BaseMail
{
    private UserDomainObject $invitedUser;

    private string $appName;

    private string $inviteLink;

    public function __construct(
        UserDomainObject $invitedUser,
        string $appName,
        string $inviteLink
    )
    {
        parent::__construct();

        $this->invitedUser = $invitedUser;
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
                'invitedUser' => $this->invitedUser,
                'appName' => $this->appName,
                'inviteLink' => $this->inviteLink
            ]
        );
    }
}
