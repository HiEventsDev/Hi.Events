<?php

namespace HiEvents\Services\Application\Handlers\OrganizerSubscription;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Mail\OrganizerSubscription\ConfirmOrganizerSubscriptionMail;
use HiEvents\Repository\Eloquent\OrganizerEventSubscriptionRepository;
use Illuminate\Contracts\Mail\Mailer;

class SubscribeToOrganizerHandler
{
    public function __construct(private readonly OrganizerEventSubscriptionRepository $subscriptions, private readonly Mailer $mailer) {}

    public function handle(int $organizerId, string $email, string $locale, string $source = 'public', ?int $orderId = null): void
    {
        if ($orderId === null && ! $this->subscriptions->isAvailable($organizerId)) {
            throw new ResourceNotFoundException(__('Subscription is unavailable.'));
        }
        $confirmation = $this->subscriptions->requestConfirmation($organizerId, $email, $locale, $source, $orderId);
        if ($confirmation !== null) {
            $this->mailer->to($confirmation->email)->locale($confirmation->locale)->send(new ConfirmOrganizerSubscriptionMail($confirmation));
        }
    }
}
