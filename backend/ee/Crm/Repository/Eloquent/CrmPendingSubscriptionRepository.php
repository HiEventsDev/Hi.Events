<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmPendingSubscriptionDomainObject;
use HiEvents\DomainObjects\Status\CrmPendingSubscriptionStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use HiEvents\Models\CrmPendingSubscription;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<CrmPendingSubscriptionDomainObject>
 */
class CrmPendingSubscriptionRepository extends BaseRepository implements CrmPendingSubscriptionRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmPendingSubscription::class;
    }

    public function getDomainObject(): string
    {
        return CrmPendingSubscriptionDomainObject::class;
    }

    public function supersedePending(int $organizerId, string $email): array
    {
        return $this->runQuery(fn () => array_values(array_filter(array_map(static fn (object $row) => $row->form_id === null ? null : (int) $row->form_id, $this->db->select('
            UPDATE crm_pending_subscriptions SET status = ?
            WHERE organizer_id = ? AND email = ? AND status = ?
            RETURNING form_id
        ', [CrmPendingSubscriptionStatus::SUPERSEDED->value, $organizerId, $email, CrmPendingSubscriptionStatus::PENDING->value])))));
    }

    public function countWaitingByForm(int $organizerId, CarbonInterface $now): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_pending_subscriptions')
            ->where('organizer_id', $organizerId)
            ->where('status', CrmPendingSubscriptionStatus::PENDING->value)
            ->where('expires_at', '>', $now)
            ->whereNotNull('form_id')
            ->groupBy('form_id')
            ->selectRaw('form_id, count(*) AS waiting')
            ->pluck('waiting', 'form_id')
            ->map(static fn ($count) => (int) $count)
            ->all());
    }

    public function findPendingForUpdate(int $pendingId): ?CrmPendingSubscriptionDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult($this->model->newQuery()
            ->where('id', $pendingId)
            ->lockForUpdate()
            ->first()));
    }

    public function pruneUnconfirmedExpiredBefore(CarbonInterface $before): int
    {
        return $this->runQuery(fn () => $this->db->table('crm_pending_subscriptions')
            ->where('status', '!=', CrmPendingSubscriptionStatus::CONFIRMED->value)
            ->where('expires_at', '<', $before)
            ->delete());
    }

    public function hasConfirmationSince(int $organizerId, string $email, CarbonInterface $since): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_pending_subscriptions')
            ->where('organizer_id', $organizerId)
            ->where('email', $email)
            ->where('status', CrmPendingSubscriptionStatus::CONFIRMED->value)
            ->where('confirmed_at', '>=', $since)
            ->exists());
    }
}
