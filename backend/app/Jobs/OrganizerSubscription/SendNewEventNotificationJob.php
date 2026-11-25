<?php

namespace HiEvents\Jobs\OrganizerSubscription;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\OrganizerSubscription\NewOrganizerEventMail;
use HiEvents\Repository\Eloquent\OrganizerEventSubscriptionRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendNewEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $eventId, public readonly int $subscriptionId) {}

    public function handle(OrganizerEventSubscriptionRepository $subscriptions, EventRepositoryInterface $events, Mailer $mailer): void
    {
        $subscriptions->deliver($this->eventId, $this->subscriptionId, function ($subscription) use ($events, $mailer) {
            $event = $events->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
                ->loadRelation(EventSettingDomainObject::class)->loadRelation(EventOccurrenceDomainObject::class)->findById($this->eventId);
            $mailer->to($subscription->email)->locale($subscription->locale)->sendNow(new NewOrganizerEventMail($event, $subscription->id));
        });
    }
}
