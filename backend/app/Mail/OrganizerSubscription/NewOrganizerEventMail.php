<?php

namespace HiEvents\Mail\OrganizerSubscription;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class NewOrganizerEventMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(private readonly EventDomainObject $event, private readonly int $subscriptionId)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('New event from :organizer', ['organizer' => $this->event->getOrganizer()->getName()]));
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.app(SubscriptionTokens::class)->oneClickUrl($this->event->getOrganizerId(), $this->subscriptionId).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $tokens = app(SubscriptionTokens::class);
        $dates = ($this->event->getEventOccurrences() ?? collect())->filter(fn ($occurrence) => ! $occurrence->isCancelled() && ! $occurrence->isPast())
            ->sortBy(fn ($occurrence) => $occurrence->getStartDate())->take(5)
            ->map(fn ($occurrence) => Carbon::parse($occurrence->getStartDate(), 'UTC')->setTimezone($this->event->getTimezone())->isoFormat('LLLL'));

        return new Content(markdown: 'emails.organizer-subscription.new-event', with: [
            'event' => $this->event, 'dates' => $dates,
            'eventUrl' => sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE), $this->event->getId(), $this->event->getSlug()),
            'unsubscribeUrl' => $tokens->pageUrl($this->event->getOrganizerId(), 'unsubscribe', $tokens->unsubscribeToken($this->subscriptionId)),
        ]);
    }
}
