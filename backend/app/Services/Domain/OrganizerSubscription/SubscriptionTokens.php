<?php

namespace HiEvents\Services\Domain\OrganizerSubscription;

use HiEvents\Helper\Url;

class SubscriptionTokens
{
    public function unsubscribeToken(int $id): string
    {
        return $id.'.'.hash_hmac('sha256', 'organizer-event-unsubscribe:'.$id, (string) config('app.key'));
    }

    public function subscriptionId(string $token): ?int
    {
        if (! preg_match('/^(\\d+)\\.[a-f0-9]{64}$/', $token, $matches)) {
            return null;
        }

        return hash_equals($this->unsubscribeToken((int) $matches[1]), $token) ? (int) $matches[1] : null;
    }

    public function pageUrl(int $organizerId, string $action, string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/organizer/$organizerId/subscriptions/$action/$token";
    }

    public function oneClickUrl(int $organizerId, int $id): string
    {
        return Url::getApiUrl("public/organizers/$organizerId/event-subscriptions/unsubscribe/".$this->unsubscribeToken($id));
    }
}
