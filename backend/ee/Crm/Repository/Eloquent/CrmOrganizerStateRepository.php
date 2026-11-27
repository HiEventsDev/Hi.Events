<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmOrganizerStateDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\CrmSignupFormStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Models\CrmOrganizerState;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmOrganizerStateDomainObject>
 */
class CrmOrganizerStateRepository extends BaseRepository implements CrmOrganizerStateRepositoryInterface
{
    private const RECENT_CAMPAIGN_DAYS = 30;

    protected function getModel(): string
    {
        return CrmOrganizerState::class;
    }

    public function getDomainObject(): string
    {
        return CrmOrganizerStateDomainObject::class;
    }

    public function createIfMissing(int $accountId, int $organizerId): CrmOrganizerStateDomainObject
    {
        $this->runQuery(fn () => $this->db->table('crm_organizer_states')->insertOrIgnore([
            CrmOrganizerStateDomainObjectAbstract::ACCOUNT_ID => $accountId,
            CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmOrganizerStateDomainObjectAbstract::CREATED_AT => now(),
            CrmOrganizerStateDomainObjectAbstract::UPDATED_AT => now(),
        ]));

        return $this->findFirstWhere([CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID => $organizerId]);
    }

    public function findOrganizerIdsAfter(int $afterStateId, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_organizer_states')
            ->where(static fn ($query) => $query
                ->whereNotNull(CrmOrganizerStateDomainObjectAbstract::SYNCED_AT)
                ->orWhereNotNull(CrmOrganizerStateDomainObjectAbstract::SYNC_JOB_UUID))
            ->where(CrmOrganizerStateDomainObjectAbstract::ID, '>', $afterStateId)
            ->orderBy(CrmOrganizerStateDomainObjectAbstract::ID)
            ->limit($limit)
            ->pluck(CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID, CrmOrganizerStateDomainObjectAbstract::ID)
            ->map(static fn ($organizerId) => (int) $organizerId));
    }

    public function advanceReconciledUntil(int $stateId, CarbonInterface $until): void
    {
        $this->runQuery(fn () => $this->db->table('crm_organizer_states')
            ->where(CrmOrganizerStateDomainObjectAbstract::ID, $stateId)
            ->where(static fn ($query) => $query
                ->whereNull(CrmOrganizerStateDomainObjectAbstract::RECONCILED_UNTIL)
                ->orWhere(CrmOrganizerStateDomainObjectAbstract::RECONCILED_UNTIL, '<', $until->toDateTimeString()))
            ->update([
                CrmOrganizerStateDomainObjectAbstract::RECONCILED_UNTIL => $until->toDateTimeString(),
                CrmOrganizerStateDomainObjectAbstract::UPDATED_AT => now(),
            ]));
    }

    public function isAudienceInUse(?int $accountId, CarbonInterface $now): bool
    {
        $forAccount = static fn ($query) => $accountId === null ? $query : $query->where('account_id', $accountId);

        return $this->runQuery(fn () => $forAccount($this->db->table('crm_contacts'))->exists()
            || $forAccount($this->db->table('crm_automations')->where('is_enabled', true)->whereNull('deleted_at'))->exists()
            || $forAccount($this->db->table('crm_signup_forms')->where('status', CrmSignupFormStatus::ACTIVE->value)->whereNull('deleted_at'))->exists()
            || $forAccount($this->db->table('crm_campaigns')->where(static fn ($campaigns) => $campaigns
                ->whereIn('status', CrmCampaignStatus::valuesOf([...CrmCampaignStatus::withdrawable(), ...CrmCampaignStatus::stoppable()]))
                ->orWhere('launched_at', '>=', $now->copy()->subDays(self::RECENT_CAMPAIGN_DAYS))))->exists());
    }

    public function hasTrackedOrganizers(): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_organizer_states')->exists());
    }

    public function findTrackedOrderId(CrmActivitySource $source, int $sourceId): ?int
    {
        [$table, $orderColumn] = match ($source) {
            CrmActivitySource::ORDER => ['orders', 'id'],
            CrmActivitySource::ATTENDEE => ['attendees', 'order_id'],
            CrmActivitySource::CHECK_IN => ['attendee_check_ins', 'order_id'],
            default => [null, null],
        };

        if ($table === null) {
            return null;
        }

        $orderId = $this->runQuery(fn () => $this->db->transaction(fn () => $this->db->table($table.' AS source')
            ->join('events', 'events.id', '=', 'source.event_id')
            ->join('crm_organizer_states', 'crm_organizer_states.organizer_id', '=', 'events.organizer_id')
            ->where('source.id', $sourceId)
            ->value('source.'.$orderColumn)));

        return $orderId === null ? null : (int) $orderId;
    }
}
