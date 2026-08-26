<?php

namespace HiEvents\Services\Domain\Account\Anonymization;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Database\DatabaseManager;

class AccountDataResolver
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function resolve(int $accountId): AnonymizationContext
    {
        $connection = $this->databaseManager->connection();

        $eventIds = $connection->table('events')
            ->where('account_id', $accountId)
            ->pluck('id')
            ->all();

        $orderIds = $eventIds === [] ? [] : $connection->table('orders')
            ->whereIn('event_id', $eventIds)
            ->pluck('id')
            ->all();

        $organizerIds = $connection->table('organizers')
            ->where('account_id', $accountId)
            ->pluck('id')
            ->all();

        $userIds = $connection->table('account_users')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->all();

        $sharedUserIds = $userIds === [] ? [] : $connection->table('account_users')
            ->whereIn('user_id', $userIds)
            ->where('account_id', '!=', $accountId)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->all();

        $soleUserIds = array_values(array_diff($userIds, $sharedUserIds));

        $soleUserEmails = $soleUserIds === [] ? [] : $connection->table('users')
            ->whereIn('id', $soleUserIds)
            ->pluck('email')
            ->all();

        $stripeAccountIds = collect()
            ->merge($connection->table('accounts')->where('id', $accountId)->pluck('stripe_account_id'))
            ->merge($connection->table('account_stripe_platforms')->where('account_id', $accountId)->pluck('stripe_account_id'))
            ->merge(
                $organizerIds === [] ? [] : $connection->table('organizer_stripe_platforms')
                    ->whereIn('organizer_id', $organizerIds)
                    ->pluck('stripe_account_id'),
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $imageEntityScopes = array_filter([
            EventDomainObject::class => $eventIds,
            OrganizerDomainObject::class => $organizerIds,
            UserDomainObject::class => $soleUserIds,
        ]);

        $imageFiles = $connection->table('images')
            ->where(function ($query) use ($accountId, $imageEntityScopes) {
                $query->where('account_id', $accountId);

                foreach ($imageEntityScopes as $entityType => $entityIds) {
                    $query->orWhere(function ($subQuery) use ($entityType, $entityIds) {
                        $subQuery->where('entity_type', $entityType)
                            ->whereIn('entity_id', $entityIds);
                    });
                }
            })
            ->get(['id', 'disk', 'path'])
            ->map(fn ($image) => ['id' => $image->id, 'disk' => $image->disk, 'path' => $image->path])
            ->all();

        return new AnonymizationContext(
            accountId: $accountId,
            eventIds: $eventIds,
            orderIds: $orderIds,
            organizerIds: $organizerIds,
            soleUserIds: $soleUserIds,
            sharedUserIds: $sharedUserIds,
            soleUserEmails: $soleUserEmails,
            stripeAccountIds: $stripeAccountIds,
            imageFiles: $imageFiles,
        );
    }
}
