<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Enums\CrmCampaignSource;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Enums\EmailBounceType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignRecipientStatus;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Models\CrmCampaign;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmCampaignDomainObject>
 */
class CrmCampaignRepository extends BaseRepository implements CrmCampaignRepositoryInterface
{
    private const UNFINISHED_RECIPIENT_STATUSES = [
        CrmCampaignRecipientStatus::PENDING,
        CrmCampaignRecipientStatus::SENDING,
    ];

    protected function getModel(): string
    {
        return CrmCampaign::class;
    }

    public function getDomainObject(): string
    {
        return CrmCampaignDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId, ?string $folder, ?CrmCampaignSource $source, ?string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $folders = $this->folders();

        if (isset($folders[$folder])) {
            $this->model = $this->model->whereIn(CrmCampaignDomainObjectAbstract::STATUS, $this->values($folders[$folder]));
        }

        if ($search !== null) {
            $pattern = '%'.addcslashes($search, '\\%_').'%';
            $this->model = $this->model->where(static fn ($query) => $query
                ->where(CrmCampaignDomainObjectAbstract::SUBJECT, 'ILIKE', $pattern)
                ->orWhere(CrmCampaignDomainObjectAbstract::NAME, 'ILIKE', $pattern));
        }

        $this->model = $this->model
            ->where(fn ($query) => $this->listed($query, $source))
            ->orderByDesc(CrmCampaignDomainObjectAbstract::ID);

        return $this->paginateWhere(
            where: [CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $organizerId],
            limit: $perPage,
            page: $page,
        );
    }

    public function countByFolder(int $organizerId): array
    {
        $byStatus = $this->runQuery(fn () => $this->db->table('crm_campaigns')
            ->where('organizer_id', $organizerId)
            ->where(fn ($query) => $this->listed($query, null))
            ->groupBy('status')
            ->selectRaw('status, (automation_id IS NOT NULL) AS is_automated, count(*) AS total')
            ->groupByRaw('automation_id IS NOT NULL')
            ->get());

        $counts = [];
        foreach ($this->folders() as $folder => $statuses) {
            $counts[$folder] = (int) $byStatus->whereIn('status', $this->values($statuses))->sum('total');
        }
        $counts['automated'] = (int) $byStatus->filter(static fn (object $row) => (bool) $row->is_automated)->sum('total');

        return $counts;
    }

    /**
     * @return array<string, CrmCampaignStatus[]>
     */
    private function folders(): array
    {
        return [
            'drafts' => CrmCampaignStatus::editable(),
            'scheduled' => CrmCampaignStatus::withdrawable(),
            'sent' => CrmCampaignStatus::launched(),
        ];
    }

    private function listed(EloquentBuilder|Builder $query, ?CrmCampaignSource $source): EloquentBuilder|Builder
    {
        return $query
            ->when($source === CrmCampaignSource::AUTOMATION, static fn ($automated) => $automated->whereNotNull('automation_id'))
            ->where(static fn ($visible) => $visible
                ->whereNull('automation_id')
                ->orWhere('status', '!=', CrmCampaignStatus::CANCELLED->value)
                ->orWhereNotNull('sent_at')
                ->orWhere('status_reason', CampaignLaunchService::REASON_REJECTED_BY_REVIEW));
    }

    public function countManualStartedSince(int $accountId, CarbonInterface $since): int
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaigns')
            ->where('account_id', $accountId)
            ->whereNull('automation_id')
            ->where('launched_at', '>=', $since)
            ->count());
    }

    public function lockAccountLaunches(int $accountId): void
    {
        $this->runQuery(fn () => $this->db->select("SELECT pg_advisory_xact_lock(hashtext('crm_campaign_launch'), ?)", [$accountId]));
    }

    public function hasSentCampaign(int $accountId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaigns')
            ->where('account_id', $accountId)
            ->where('status', CrmCampaignStatus::SENT->value)
            ->exists());
    }

    public function findDueScheduledIds(CarbonInterface $now, int $limit): array
    {
        return $this->runQuery(fn () => $this->ofLiveOrganizers()
            ->where(CrmCampaignDomainObjectAbstract::STATUS, CrmCampaignStatus::SCHEDULED->value)
            ->where(CrmCampaignDomainObjectAbstract::SCHEDULED_AT, '<=', $now->toDateTimeString())
            ->orderBy(CrmCampaignDomainObjectAbstract::SCHEDULED_AT)
            ->orderBy(CrmCampaignDomainObjectAbstract::ID)
            ->limit($limit)
            ->pluck(CrmCampaignDomainObjectAbstract::ID)
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findStaleReviews(CarbonInterface $scheduledBefore, CarbonInterface $automationLaunchedBefore, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults($this->model->newQuery()
            ->where(CrmCampaignDomainObjectAbstract::STATUS, CrmCampaignStatus::PENDING_REVIEW->value)
            ->where(static fn ($stale) => $stale
                ->where(static fn ($scheduled) => $scheduled
                    ->whereNull(CrmCampaignDomainObjectAbstract::AUTOMATION_ID)
                    ->where(CrmCampaignDomainObjectAbstract::SCHEDULED_AT, '<', $scheduledBefore->toDateTimeString()))
                ->orWhere(static fn ($automated) => $automated
                    ->whereNotNull(CrmCampaignDomainObjectAbstract::AUTOMATION_ID)
                    ->where(CrmCampaignDomainObjectAbstract::LAUNCHED_AT, '<', $automationLaunchedBefore->toDateTimeString())))
            ->orderBy(CrmCampaignDomainObjectAbstract::ID)
            ->limit($limit)
            ->get()));
    }

    public function findForLaunch(int $campaignId): ?CrmCampaignDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult(
            $this->model->newQuery()->whereKey($campaignId)->lockForUpdate()->first(),
        ));
    }

    public function transitionStatus(int $campaignId, array $fromStatuses, array $attributes, ?CarbonInterface $onlyIfScheduledBy = null, ?CarbonInterface $onlyIfLastUpdatedAt = null): bool
    {
        return $this->runQuery(fn () => $this->model->newQuery()
            ->where(CrmCampaignDomainObjectAbstract::ID, $campaignId)
            ->whereIn(CrmCampaignDomainObjectAbstract::STATUS, $fromStatuses)
            ->when($onlyIfScheduledBy !== null, static fn ($query) => $query->where(CrmCampaignDomainObjectAbstract::SCHEDULED_AT, '<=', $onlyIfScheduledBy))
            ->when($onlyIfLastUpdatedAt !== null, static fn ($query) => $query->where(CrmCampaignDomainObjectAbstract::UPDATED_AT, $onlyIfLastUpdatedAt->copy()->utc()->toDateTimeString()))
            ->update($this->encoded($attributes)) === 1);
    }

    public function updateIfEditable(int $campaignId, array $attributes): bool
    {
        return $this->runQuery(fn () => $this->model->newQuery()
            ->where(CrmCampaignDomainObjectAbstract::ID, $campaignId)
            ->whereIn(CrmCampaignDomainObjectAbstract::STATUS, CrmCampaignStatus::editableValues())
            ->update($this->encoded($attributes)) === 1);
    }

    private function encoded(array $attributes): array
    {
        return array_map(static fn ($value) => is_array($value) ? json_encode($value) : $value, $attributes);
    }

    public function touchIfStatus(int $campaignId, CrmCampaignStatus $status): bool
    {
        return $this->runQuery(fn () => $this->model->newQuery()
            ->where(CrmCampaignDomainObjectAbstract::ID, $campaignId)
            ->where(CrmCampaignDomainObjectAbstract::STATUS, $status->value)
            ->update([CrmCampaignDomainObjectAbstract::UPDATED_AT => now()->toDateTimeString()]) === 1);
    }

    public function recordAiReview(int $campaignId, array $verdict, CarbonInterface $reviewedVersion): bool
    {
        return $this->runQuery(fn () => $this->model->newQuery()
            ->where(CrmCampaignDomainObjectAbstract::ID, $campaignId)
            ->where(CrmCampaignDomainObjectAbstract::STATUS, CrmCampaignStatus::PENDING_REVIEW->value)
            ->where(CrmCampaignDomainObjectAbstract::UPDATED_AT, $reviewedVersion->copy()->utc()->toDateTimeString())
            ->update([
                CrmCampaignDomainObjectAbstract::AI_REVIEW => json_encode($verdict),
                CrmCampaignDomainObjectAbstract::UPDATED_AT => now()->toDateTimeString(),
            ]) === 1);
    }

    public function snapshotRecipients(int $campaignId, int $organizerId, ?CompiledSegmentDTO $segment, CarbonInterface $now): int
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($campaignId, $organizerId, $segment, $now): int {
            $this->db->insert('
                INSERT INTO crm_campaign_recipients (campaign_id, organizer_id, contact_id, email, status, created_at, updated_at)
                SELECT ?, crm_contacts.organizer_id, crm_contacts.id, crm_contacts.email, ?, ?, ?
                FROM crm_contacts
                WHERE crm_contacts.organizer_id = ?
                  AND crm_contacts.email_marketing_status = ?
                  AND '.($segment?->sql ?? 'TRUE').'
                ON CONFLICT (campaign_id, email) DO NOTHING
            ', [
                $campaignId,
                CrmCampaignRecipientStatus::PENDING->value,
                $now->toDateTimeString(),
                $now->toDateTimeString(),
                $organizerId,
                CrmEmailMarketingStatus::SUBSCRIBED->value,
                ...($segment?->bindings ?? []),
            ]);

            $count = $this->db->table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count();

            $this->db->table('crm_campaigns')->where('id', $campaignId)->update(['recipients_count' => $count]);

            return $count;
        }));
    }

    public function snapshotSmsRecipients(int $campaignId, int $organizerId, ?CompiledSegmentDTO $segment, CarbonInterface $now, array $countries): int
    {
        if ($countries === []) {
            return 0;
        }

        return $this->runQuery(fn () => $this->db->transaction(function () use ($campaignId, $organizerId, $segment, $now, $countries): int {
            $this->db->insert('
                INSERT INTO crm_campaign_recipients (campaign_id, organizer_id, contact_id, email, phone, phone_country, status, created_at, updated_at)
                SELECT DISTINCT ON (crm_contacts.phone) ?, crm_contacts.organizer_id, crm_contacts.id, crm_contacts.email, crm_contacts.phone, crm_contacts.phone_country, ?, ?, ?
                FROM crm_contacts
                WHERE crm_contacts.organizer_id = ?
                  AND crm_contacts.sms_marketing_status = ?
                  AND crm_contacts.phone IS NOT NULL
                  AND crm_contacts.phone_country = ANY(CAST(? AS text[]))
                  AND '.($segment?->sql ?? 'TRUE').'
                ORDER BY crm_contacts.phone, crm_contacts.last_activity_at DESC NULLS LAST, crm_contacts.id
                ON CONFLICT (campaign_id, email) DO NOTHING
            ', [
                $campaignId,
                CrmCampaignRecipientStatus::PENDING->value,
                $now->toDateTimeString(),
                $now->toDateTimeString(),
                $organizerId,
                CrmSmsMarketingStatus::SUBSCRIBED->value,
                '{'.implode(',', $countries).'}',
                ...($segment?->bindings ?? []),
            ]);

            $count = $this->db->table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count();

            $this->db->table('crm_campaigns')->where('id', $campaignId)->update(['recipients_count' => $count]);

            return $count;
        }));
    }

    public function recordSmsDeliveryStatus(int $organizerId, string $providerMessageId, bool $delivered, CarbonInterface $now): ?int
    {
        return $this->runQuery(function () use ($organizerId, $providerMessageId, $delivered, $now): ?int {
            $recipient = $this->db->table('crm_campaign_recipients')
                ->where('organizer_id', $organizerId)
                ->where('provider_message_id', $providerMessageId)
                ->whereNotNull('phone_country')
                ->where('status', CrmCampaignRecipientStatus::SENT->value)
                ->first(['id', 'campaign_id']);

            if ($recipient === null) {
                return null;
            }

            $this->db->table('crm_campaign_recipients')
                ->where('id', $recipient->id)
                ->where('status', CrmCampaignRecipientStatus::SENT->value)
                ->update($delivered
                    ? ['delivered_at' => $now, 'updated_at' => $now]
                    : ['status' => CrmCampaignRecipientStatus::FAILED->value, 'failure_reason' => CrmRecipientFailureReason::UNDELIVERED->value, 'updated_at' => $now]);

            return (int) $recipient->campaign_id;
        });
    }

    public function findPendingRecipientCountries(int $campaignId): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->whereNotNull('phone_country')
            ->distinct()
            ->pluck('phone_country')
            ->all());
    }

    public function countPendingRecipientsByCountry(array $campaignIds): Collection
    {
        if ($campaignIds === []) {
            return collect();
        }

        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->whereNotNull('phone_country')
            ->groupBy('campaign_id', 'phone_country')
            ->orderBy('campaign_id')
            ->orderBy('phone_country')
            ->selectRaw('campaign_id, phone_country, count(*) AS pending_count')
            ->get());
    }

    public function findPendingRecipientIds(int $campaignId, int $afterRecipientId, int $limit, ?array $countries = null): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->where('id', '>', $afterRecipientId)
            ->when($countries !== null, static fn ($query) => $query->whereIn('phone_country', $countries))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findRecipient(int $recipientId): ?object
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->leftJoin('crm_contacts', 'crm_contacts.id', '=', 'crm_campaign_recipients.contact_id')
            ->where('crm_campaign_recipients.id', $recipientId)
            ->first([
                'crm_campaign_recipients.id',
                'crm_campaign_recipients.campaign_id',
                'crm_campaign_recipients.organizer_id',
                'crm_campaign_recipients.contact_id',
                'crm_campaign_recipients.email',
                'crm_campaign_recipients.phone',
                'crm_campaign_recipients.phone_country',
                'crm_campaign_recipients.status',
                'crm_contacts.email AS contact_email',
                'crm_contacts.email_marketing_status',
                'crm_contacts.phone AS contact_phone',
                'crm_contacts.sms_marketing_status',
                'crm_contacts.locale',
                'crm_contacts.first_name',
                'crm_contacts.last_name',
            ]));
    }

    public function markRecipientSent(int $recipientId, ?string $providerMessageId, CarbonInterface $now): void
    {
        $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')->where('id', $recipientId)->update([
            'status' => CrmCampaignRecipientStatus::SENT->value,
            'provider_message_id' => $providerMessageId,
            'sent_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public function markRecipientSkipped(int $recipientId, CrmRecipientFailureReason $reason): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('id', $recipientId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->update([
                'status' => CrmCampaignRecipientStatus::SKIPPED->value,
                'failure_reason' => $reason->value,
                'updated_at' => now(),
            ]) === 1);
    }

    public function markRecipientFailed(int $recipientId, CrmRecipientFailureReason $reason): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('id', $recipientId)
            ->whereIn('status', $this->values(self::UNFINISHED_RECIPIENT_STATUSES))
            ->update([
                'status' => CrmCampaignRecipientStatus::FAILED->value,
                'failure_reason' => $reason->value,
                'updated_at' => now(),
            ]) === 1);
    }

    public function skipPendingRecipients(int $campaignId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->update([
                'status' => CrmCampaignRecipientStatus::SKIPPED->value,
                'failure_reason' => CrmRecipientFailureReason::CAMPAIGN_STOPPED->value,
                'updated_at' => now(),
            ]));
    }

    public function recordCampaignActivity(int $recipientId, CarbonInterface $now): void
    {
        $this->runQuery(fn () => $this->db->insert('
            INSERT INTO crm_contact_activities (organizer_id, contact_id, type, source_type, source_id, label, email, occurred_at)
            SELECT r.organizer_id, r.contact_id, ?, ?, r.id, c.name, r.email, ?
            FROM crm_campaign_recipients r
            JOIN crm_campaigns c ON c.id = r.campaign_id
            JOIN crm_contacts contact ON contact.id = r.contact_id
            WHERE r.id = ?
            ON CONFLICT DO NOTHING
        ', [
            CrmActivityType::CAMPAIGN_SENT->value,
            CrmActivitySource::CAMPAIGN_RECIPIENT->value,
            $now->toDateTimeString(),
            $recipientId,
        ]));
    }

    public function hasPendingRecipients(int $campaignId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->whereIn('status', $this->values(self::UNFINISHED_RECIPIENT_STATUSES))
            ->exists());
    }

    public function countPendingRecipientsBetween(int $campaignId, int $afterRecipientId, int $upToRecipientId, ?array $countries = null): int
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('id', '>', $afterRecipientId)
            ->where('id', '<=', $upToRecipientId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->when($countries !== null, static fn ($query) => $query->whereIn('phone_country', $countries))
            ->count());
    }

    public function unclaimRecipient(int $recipientId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('id', $recipientId)
            ->where('status', CrmCampaignRecipientStatus::SENDING->value)
            ->update(['status' => CrmCampaignRecipientStatus::PENDING->value, 'updated_at' => now()]));
    }

    public function claimRecipient(int $recipientId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->where('id', $recipientId)
            ->where('status', CrmCampaignRecipientStatus::PENDING->value)
            ->update(['status' => CrmCampaignRecipientStatus::SENDING->value, 'updated_at' => now()]) === 1);
    }

    public function failStalledRecipients(CarbonInterface $stalledBefore): void
    {
        $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')
            ->whereRaw("status = '".CrmCampaignRecipientStatus::SENDING->value."'")
            ->where('updated_at', '<', $stalledBefore)
            ->update([
                'status' => CrmCampaignRecipientStatus::FAILED->value,
                'failure_reason' => CrmRecipientFailureReason::TIMEOUT->value,
            ]));
    }

    public function findStalledCampaignIds(CarbonInterface $stalledBefore, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaigns AS c')
            ->where('c.status', CrmCampaignStatus::SENDING->value)
            ->where('c.updated_at', '<', $stalledBefore)
            ->whereNotExists(static fn ($recent) => $recent
                ->selectRaw('1')
                ->from('crm_campaign_recipients AS r')
                ->whereColumn('r.campaign_id', 'c.id')
                ->where('r.updated_at', '>=', $stalledBefore))
            ->orderBy('c.id')
            ->limit($limit)
            ->pluck('c.id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function hasRecipients(int $campaignId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients')->where('campaign_id', $campaignId)->exists());
    }

    public function withdrawRecipients(int $campaignId, bool $keepAsSkipped = false): void
    {
        if ($keepAsSkipped) {
            $this->skipPendingRecipients($campaignId);

            return;
        }

        $this->runQuery(function () use ($campaignId): void {
            $this->db->table('crm_campaign_recipients')->where('campaign_id', $campaignId)->delete();
            $this->db->table('crm_campaigns')->where('id', $campaignId)->update(['recipients_count' => 0]);
        });
    }

    public function findRecipients(int $campaignId, ?string $filter, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($campaignId, $filter, $page, $perPage) {
            $query = $this->db->table('crm_campaign_recipients AS r')
                ->leftJoin('email_deliveries AS d', static fn ($join) => $join
                    ->on('d.provider_message_id', '=', 'r.provider_message_id')
                    ->whereNotNull('r.provider_message_id'))
                ->leftJoin('crm_contacts AS c', 'c.id', '=', 'r.contact_id')
                ->where('r.campaign_id', $campaignId);

            match ($filter) {
                'delivered' => $query->whereRaw('coalesce(d.delivered_at, r.delivered_at) IS NOT NULL'),
                'opened' => $query->whereNotNull('d.first_opened_at'),
                'clicked' => $query->whereNotNull('d.first_clicked_at'),
                'bounced' => $query->where(static fn ($bounced) => $bounced->whereNotNull('d.bounced_at')->orWhereNotNull('d.complained_at')),
                'not_sent' => $query->whereIn('r.status', [CrmCampaignRecipientStatus::FAILED->value, CrmCampaignRecipientStatus::SKIPPED->value]),
                default => $query,
            };

            return $query
                ->orderBy('r.id')
                ->paginate(
                    perPage: min($perPage, 100),
                    columns: [
                        'r.id',
                        'r.contact_id',
                        'r.email',
                        'r.phone',
                        'r.status',
                        'r.failure_reason',
                        'r.sent_at',
                        'c.first_name',
                        'c.last_name',
                        $this->db->raw('coalesce(d.delivered_at, r.delivered_at) AS delivered_at'),
                        'd.first_opened_at',
                        'd.first_clicked_at',
                        'd.bounced_at',
                        'd.complained_at',
                    ],
                    page: $page,
                );
        });
    }

    public function getDeliveryStats(array $campaignIds): Collection
    {
        if ($campaignIds === []) {
            return collect();
        }

        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients AS r')
            ->leftJoin('email_deliveries AS d', static fn ($join) => $join
                ->on('d.provider_message_id', '=', 'r.provider_message_id')
                ->whereNotNull('r.provider_message_id'))
            ->whereIn('r.campaign_id', $campaignIds)
            ->groupBy('r.campaign_id')
            ->selectRaw('
                r.campaign_id,
                count(*) AS recipients,
                count(*) FILTER (WHERE r.status = ?) AS sent,
                count(*) FILTER (WHERE r.status = ?) AS failed,
                count(*) FILTER (WHERE r.status = ?) AS skipped,
                count(*) FILTER (WHERE r.status IN (?, ?)) AS pending,
                count(coalesce(d.delivered_at, r.delivered_at)) AS delivered,
                count(d.first_opened_at) AS opened,
                count(d.first_clicked_at) AS clicked,
                count(d.bounced_at) AS bounced,
                count(d.complained_at) AS complained
            ', [
                CrmCampaignRecipientStatus::SENT->value,
                CrmCampaignRecipientStatus::FAILED->value,
                CrmCampaignRecipientStatus::SKIPPED->value,
                CrmCampaignRecipientStatus::PENDING->value,
                CrmCampaignRecipientStatus::SENDING->value,
            ])
            ->get()
            ->keyBy('campaign_id'));
    }

    public function getHealthStats(int $campaignId): object
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients AS r')
            ->leftJoin('email_deliveries AS d', 'd.provider_message_id', '=', 'r.provider_message_id')
            ->where('r.campaign_id', $campaignId)
            ->where(static fn ($query) => $query
                ->where('r.status', CrmCampaignRecipientStatus::SENT->value)
                ->orWhere('r.failure_reason', CrmRecipientFailureReason::PROVIDER_SUPPRESSED->value))
            ->selectRaw('
                count(*) AS sent,
                count(d.complained_at) AS complained,
                count(d.bounced_at) FILTER (WHERE d.bounce_type = ?) + count(*) FILTER (WHERE r.failure_reason = ?) AS bounced
            ', [EmailBounceType::PERMANENT->name, CrmRecipientFailureReason::PROVIDER_SUPPRESSED->value])
            ->first());
    }

    public function getAttributedRevenue(CrmCampaignDomainObject $campaign): Collection
    {
        if ($campaign->getSentAt() === null) {
            return collect();
        }

        return $this->runQuery(fn () => collect($this->db->select('
            WITH orders AS (
                SELECT
                    a.id,
                    a.amount - coalesce((
                        SELECT refund.amount FROM crm_contact_activities refund
                        WHERE refund.contact_id = a.contact_id AND refund.type = :refund_type
                          AND refund.source_type = a.source_type AND refund.source_id = a.source_id
                    ), 0) AS amount,
                    a.currency,
                    (
                        coalesce(a.promo_code_id = CAST(:promo_match AS bigint), false)
                        OR EXISTS (
                            SELECT 1
                            FROM crm_campaign_recipients r
                            JOIN email_deliveries d ON d.provider_message_id = r.provider_message_id
                            WHERE r.campaign_id = :clicked_campaign
                              AND r.contact_id = a.contact_id
                              AND r.sent_at <= a.occurred_at
                              AND d.first_clicked_at IS NOT NULL
                              AND d.first_clicked_at <= a.occurred_at
                        )
                    ) AS attributed,
                    EXISTS (
                        SELECT 1 FROM crm_campaign_recipients r
                        WHERE r.campaign_id = :recipient_campaign AND r.contact_id = a.contact_id AND r.status = :sent_status
                          AND r.sent_at <= a.occurred_at
                    ) AS received
                FROM crm_contact_activities a
                WHERE a.organizer_id = :organizer
                  AND a.type = :type
                  AND a.occurred_at >= :sent_at
                  AND a.occurred_at <= :until
            )
            SELECT
                currency,
                count(*) FILTER (WHERE attributed) AS attributed_orders,
                coalesce(sum(amount) FILTER (WHERE attributed), 0) AS attributed_revenue,
                count(*) FILTER (WHERE NOT attributed AND received) AS influenced_orders,
                coalesce(sum(amount) FILTER (WHERE NOT attributed AND received), 0) AS influenced_revenue
            FROM orders
            WHERE attributed OR received
            GROUP BY currency
            ORDER BY coalesce(sum(amount), 0) DESC
        ', [
            'refund_type' => CrmActivityType::ORDER_REFUNDED->value,
            'promo_match' => $campaign->getPromoCodeId(),
            'clicked_campaign' => $campaign->getId(),
            'recipient_campaign' => $campaign->getId(),
            'sent_status' => CrmCampaignRecipientStatus::SENT->value,
            'organizer' => $campaign->getOrganizerId(),
            'type' => CrmActivityType::ORDER_COMPLETED->value,
            'sent_at' => $campaign->getSentAt(),
            'until' => Carbon::parse($campaign->getSentAt())->addDays($campaign->getAttributionWindowDays())->toDateTimeString(),
        ])));
    }

    public function findUnrecordedFeedback(CarbonInterface $recordedSince, int $limit): Collection
    {
        $tombstoneStrength = 'CASE tombstone.suppression';
        foreach (CrmSuppressionRepository::SUPPRESSED_STATUSES as $status) {
            $tombstoneStrength .= " WHEN '{$status->value}' THEN {$status->suppressionStrength()}";
        }
        $tombstoneStrength .= ' ELSE 0 END';
        $feedbackStrength = 'CASE WHEN d.complained_at IS NOT NULL THEN '.CrmEmailMarketingStatus::COMPLAINED->suppressionStrength()
            .' ELSE '.CrmEmailMarketingStatus::BOUNCED->suppressionStrength().' END';

        return $this->runQuery(fn () => collect($this->db->select('
            SELECT
                r.id AS recipient_id,
                r.campaign_id,
                r.organizer_id,
                r.contact_id,
                r.email,
                d.complained_at,
                d.bounced_at
            FROM email_deliveries d
            JOIN crm_campaign_recipients r ON r.provider_message_id = d.provider_message_id
            LEFT JOIN crm_contacts c ON c.id = r.contact_id
            LEFT JOIN crm_deleted_contacts tombstone ON r.contact_id IS NULL
                AND tombstone.organizer_id = r.organizer_id
                AND '.DeletedContactHasher::isRecipientPlaceholderSql('r.email').'
                AND tombstone.email_hash = '.DeletedContactHasher::hashFromRecipientPlaceholderSql('r.email')."
            WHERE d.updated_at >= ?
              AND (d.complained_at IS NOT NULL OR (d.bounced_at IS NOT NULL AND d.bounce_type = '".EmailBounceType::PERMANENT->name."'))
              AND (
                  (c.id IS NOT NULL AND NOT EXISTS (
                      SELECT 1 FROM crm_consent_events ev
                      WHERE ev.contact_id = r.contact_id AND ev.source_type = ? AND ev.source_id = r.id
                        AND ev.action = CASE WHEN d.complained_at IS NOT NULL THEN ? ELSE ? END
                  ))
                  OR (tombstone.id IS NOT NULL AND $tombstoneStrength < $feedbackStrength)
              )
            ORDER BY r.id
            LIMIT ?
        ", [
            $recordedSince->toDateTimeString(),
            CrmConsentSource::CAMPAIGN->value,
            CrmConsentAction::COMPLAINED->value,
            CrmConsentAction::BOUNCED->value,
            $limit,
        ])));
    }

    /**
     * @param  array<int, CrmCampaignStatus|CrmCampaignRecipientStatus>  $statuses
     * @return string[]
     */
    private function values(array $statuses): array
    {
        return array_map(static fn (CrmCampaignStatus|CrmCampaignRecipientStatus $status) => $status->value, $statuses);
    }

    public function findPendingReview(): Collection
    {
        return $this->runQuery(fn () => $this->handleResults($this->ofLiveOrganizers()
            ->where(CrmCampaignDomainObjectAbstract::STATUS, CrmCampaignStatus::PENDING_REVIEW->value)
            ->get()));
    }

    public function countImportOnlyRecipients(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->db->table('crm_campaign_recipients AS r')
            ->join('crm_contacts AS c', 'c.id', '=', 'r.contact_id')
            ->whereIn('r.campaign_id', $campaignIds)
            ->whereNotExists(static fn ($optIns) => $optIns
                ->selectRaw('1')
                ->from('crm_consent_events AS ev')
                ->whereColumn('ev.contact_id', 'c.id')
                ->whereColumn('ev.email', 'c.email')
                ->where('ev.action', CrmConsentAction::OPT_IN->value)
                ->where('ev.source_type', '<>', CrmConsentSource::IMPORT->value))
            ->groupBy('r.campaign_id')
            ->selectRaw('r.campaign_id, count(*) AS recipients')
            ->pluck('recipients', 'campaign_id')
            ->map(static fn ($count) => (int) $count)
            ->all());
    }

    private function ofLiveOrganizers(): EloquentBuilder
    {
        return $this->model->newQuery()->whereExists(static fn ($organizers) => $organizers
            ->selectRaw('1')
            ->from('organizers')
            ->whereColumn('organizers.id', 'crm_campaigns.organizer_id')
            ->whereNull('organizers.deleted_at'));
    }

    public function addExplicitAudience(int $campaignId, array $contactIds): void
    {
        $this->runQuery(function () use ($campaignId, $contactIds): void {
            foreach (array_chunk(array_values(array_unique($contactIds)), 1000) as $chunk) {
                $this->db->table('crm_campaign_audience_contacts')->insertOrIgnore(array_map(
                    static fn (int $contactId) => ['campaign_id' => $campaignId, 'contact_id' => $contactId],
                    $chunk,
                ));
            }
        });
    }

    public function hasBoughtSince(int $organizerId, int $contactId, int $eventId, string $since): bool
    {
        return $this->runQuery(fn () => $this->db->table('orders')
            ->join('crm_contact_emails', fn ($join) => $join
                ->on('crm_contact_emails.email', '=', $this->db->raw('lower(btrim(orders.email, '.ContactEmailNormalizer::SQL_TRIMMED_CHARACTERS.'))'))
                ->where('crm_contact_emails.organizer_id', $organizerId))
            ->where('crm_contact_emails.contact_id', $contactId)
            ->where('orders.event_id', $eventId)
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', [OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name])
            ->where('orders.created_at', '>=', $since)
            ->exists());
    }

    public function findEventCard(int $eventId, ?int $occurrenceId, CarbonInterface $now): ?object
    {
        return $this->runQuery(fn () => $this->db->selectOne("
            SELECT e.id, e.title, e.timezone, occ.start_date, img.path AS cover_path,
                   el.type AS location_type, l.name AS location_name, l.structured_address->>'city' AS city
            FROM events e
            LEFT JOIN LATERAL (
                SELECT o.start_date, o.event_location_id
                FROM event_occurrences o
                WHERE o.event_id = e.id AND o.deleted_at IS NULL AND (
                    (CAST(:occurrence AS bigint) IS NOT NULL AND o.id = CAST(:occurrence AS bigint))
                    OR (CAST(:occurrence AS bigint) IS NULL AND o.status = :active AND (o.start_date >= :now OR o.end_date >= :now))
                )
                ORDER BY o.start_date
                LIMIT 1
            ) occ ON true
            LEFT JOIN LATERAL (
                SELECT i.path FROM images i
                WHERE i.entity_id = e.id AND i.entity_type = :entity_type AND i.type = :cover AND i.deleted_at IS NULL
                ORDER BY i.id DESC
                LIMIT 1
            ) img ON true
            LEFT JOIN LATERAL (
                SELECT x.type, x.location_id FROM event_locations x
                WHERE x.deleted_at IS NULL AND (x.id = occ.event_location_id OR (occ.event_location_id IS NULL AND x.event_id = e.id))
                ORDER BY x.id
                LIMIT 1
            ) el ON true
            LEFT JOIN locations l ON l.id = el.location_id
            WHERE e.id = :event AND e.deleted_at IS NULL
        ", [
            'occurrence' => $occurrenceId,
            'active' => EventOccurrenceStatus::ACTIVE->name,
            'now' => $now->toDateTimeString(),
            'entity_type' => EventDomainObject::class,
            'cover' => ImageType::EVENT_COVER->name,
            'event' => $eventId,
        ]));
    }

    public function findLastAttendedEvent(int $contactId): ?object
    {
        return $this->runQuery(fn () => $this->db->selectOne('
            SELECT e.title, e.timezone, coalesce(o.start_date, a.occurred_at) AS starts_at
            FROM crm_contact_activities a
            JOIN events e ON e.id = a.event_id AND e.deleted_at IS NULL
            LEFT JOIN event_occurrences o ON o.id = a.event_occurrence_id
            WHERE a.contact_id = ? AND a.type = ?
            ORDER BY a.occurred_at DESC, a.id DESC
            LIMIT 1
        ', [$contactId, CrmActivityType::CHECKED_IN->value]));
    }

    public function hasCurrentOrUpcomingDate(int $eventId, CarbonInterface $now): bool
    {
        return $this->runQuery(fn () => $this->currentOrUpcomingOccurrences($eventId, $now)->exists());
    }

    public function findCurrentOrNextOccurrenceStart(int $eventId, CarbonInterface $now): ?string
    {
        return $this->runQuery(fn () => $this->currentOrUpcomingOccurrences($eventId, $now)->min('start_date')
            ?? $this->activeOccurrences($eventId)->max('start_date'));
    }

    private function currentOrUpcomingOccurrences(int $eventId, CarbonInterface $now): Builder
    {
        return $this->activeOccurrences($eventId)->where(static fn ($query) => $query
            ->where('start_date', '>=', $now)
            ->orWhere('end_date', '>=', $now));
    }

    private function activeOccurrences(int $eventId): Builder
    {
        return $this->db->table('event_occurrences')
            ->where('event_id', $eventId)
            ->where('status', EventOccurrenceStatus::ACTIVE->name)
            ->whereNull('deleted_at');
    }
}
