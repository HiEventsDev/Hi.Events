<?php

namespace HiEvents\Mail\OrganizerSubscription;

use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\OrganizerSubscription\DTO\SubscriptionConfirmationData;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmOrganizerSubscriptionMail extends BaseMail
{
    public function __construct(private readonly SubscriptionConfirmationData $confirmation)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirm your event subscription'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.organizer-subscription.confirm', with: [
            'confirmationUrl' => app(SubscriptionTokens::class)->pageUrl($this->confirmation->organizerId, 'confirm', $this->confirmation->token),
        ]);
    }
}
