<?php

namespace HiEvents\Services\Application\Handlers\OrganizerSubscription;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\OrganizerEventSubscriptionRepository;
use HiEvents\Services\Domain\OrganizerSubscription\DTO\SubscriberCountsData;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageOrganizerSubscriptionsHandler
{
    public function __construct(private readonly OrganizerEventSubscriptionRepository $subscriptions, private readonly SubscriptionTokens $tokens) {}

    public function subscribers(int $organizerId, ?string $search, ?string $status, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->subscriptions->subscribers($organizerId, $search, $status, $perPage, $page);
    }

    public function counts(int $organizerId): SubscriberCountsData
    {
        return $this->subscriptions->counts($organizerId);
    }

    public function export(int $organizerId, ?string $search, ?string $status): iterable
    {
        return $this->subscriptions->export($organizerId, $search, $status);
    }

    public function unsubscribeIds(int $organizerId, array $ids): void
    {
        $this->subscriptions->unsubscribe($organizerId, $ids);
    }

    public function confirm(int $organizerId, string $token): void
    {
        $this->subscriptions->confirm($organizerId, $token);
    }

    public function unsubscribe(int $organizerId, string $token): void
    {
        $id = $this->tokens->subscriptionId($token);
        if ($id === null || ! $this->subscriptions->findFirstWhere(['id' => $id, 'organizer_id' => $organizerId])) {
            throw new ResourceNotFoundException(__('This unsubscribe link is invalid.'));
        }
        $this->subscriptions->unsubscribe($organizerId, [$id]);
    }
}
