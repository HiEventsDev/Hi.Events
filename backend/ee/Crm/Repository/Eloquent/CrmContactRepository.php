<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmContactSource;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmDeletedContactSaltDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\DeletedContactConsentDTO;
use HiEvents\Enterprise\Crm\Repository\DTO\ImportChunkResultDTO;
use HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns\FindsTombstones;
use HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns\LocksCrmOrganizer;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentStatusSql;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentQueryCompiler;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CrmContact;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmContactDomainObject>
 */
class CrmContactRepository extends BaseRepository implements CrmContactRepositoryInterface
{
    use FindsTombstones;
    use LocksCrmOrganizer;

    private const CONSENT_ACTION_BY_STATUS = [
        CrmEmailMarketingStatus::COMPLAINED->value => CrmConsentAction::COMPLAINED->value,
        CrmEmailMarketingStatus::BOUNCED->value => CrmConsentAction::BOUNCED->value,
        CrmEmailMarketingStatus::SUBSCRIBED->value => CrmConsentAction::OPT_IN->value,
        CrmEmailMarketingStatus::UNSUBSCRIBED->value => CrmConsentAction::OPT_OUT->value,
    ];

    protected function getModel(): string
    {
        return CrmContact::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId, QueryParamsDTO $params, ?CompiledSegmentDTO $segment): LengthAwarePaginator
    {
        $where = [
            [CrmContactDomainObjectAbstract::ORGANIZER_ID, '=', $organizerId],
        ];

        if (! empty($params->query)) {
            $where[] = fn (Builder $builder) => $builder->whereIn(
                'crm_contacts.id',
                $this->contactIdsMatching($organizerId, $params->query),
            );
        }

        if ($segment !== null) {
            $this->model = $this->model->whereRaw($segment->sql, $segment->bindings);
        }

        $sortColumn = $this->validateSortColumn($params->sort_by, CrmContactDomainObject::class);
        $sortDirection = $this->validateSortDirection($params->sort_direction, CrmContactDomainObject::class);

        $this->model = $this->model
            ->orderByRaw($sortColumn.' '.($sortDirection === 'asc' ? 'ASC' : 'DESC').' NULLS LAST')
            ->orderBy(CrmContactDomainObjectAbstract::ID, 'desc');

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findForExport(int $organizerId, ?string $query, ?CompiledSegmentDTO $segment, ?string $afterEmail, int $limit, ?array $contactIds = null): Collection
    {
        return $this->runQuery(fn () => $this->handleResults($this->model->newQuery()
            ->where(CrmContactDomainObjectAbstract::ORGANIZER_ID, $organizerId)
            ->when($contactIds !== null, static fn ($builder) => $builder->whereIn(CrmContactDomainObjectAbstract::ID, $contactIds))
            ->when(! empty($query), fn ($builder) => $builder->whereIn('crm_contacts.id', $this->contactIdsMatching($organizerId, (string) $query)))
            ->when($segment !== null, static fn ($builder) => $builder->whereRaw($segment->sql, $segment->bindings))
            ->when($afterEmail !== null, static fn ($builder) => $builder->where(CrmContactDomainObjectAbstract::EMAIL, '>', $afterEmail))
            ->orderBy(CrmContactDomainObjectAbstract::EMAIL)
            ->limit($limit)
            ->get()));
    }

    private function contactIdsMatching(int $organizerId, string $query): QueryBuilder
    {
        $term = '%'.addcslashes(trim($query), '\\%_').'%';

        return $this->db->table('crm_contact_emails')
            ->select('contact_id')
            ->where('organizer_id', $organizerId)
            ->where('email', 'ilike', $term)
            ->unionAll($this->db->table('crm_contacts')
                ->select('id')
                ->where('organizer_id', $organizerId)
                ->whereRaw("(coalesce(first_name, '') || ' ' || coalesce(last_name, '')) ilike ?", [$term]));
    }

    public function findContactIdByEmail(int $organizerId, string $email): ?int
    {
        $contactId = $this->runQuery(fn () => $this->db->table('crm_contact_emails')
            ->where('organizer_id', $organizerId)
            ->where('email', $email)
            ->value('contact_id'));

        return $contactId === null ? null : (int) $contactId;
    }

    public function findTagsForContacts(array $contactIds): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_tags')
            ->join('crm_tags', 'crm_tags.id', '=', 'crm_contact_tags.tag_id')
            ->whereIn('crm_contact_tags.contact_id', $contactIds)
            ->orderBy('crm_tags.name')
            ->get(['crm_contact_tags.contact_id', 'crm_tags.id', 'crm_tags.name', 'crm_tags.color'])
            ->groupBy('contact_id'));
    }

    public function findSpendForContacts(array $contactIds): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_spend')
            ->whereIn('contact_id', $contactIds)
            ->orderByDesc('total_gross')
            ->get(['contact_id', 'currency', 'total_gross', 'total_refunded'])
            ->groupBy('contact_id'));
    }

    public function findEmails(int $contactId): Collection
    {
        $optIn = CrmConsentAction::OPT_IN->value;
        $optOut = CrmConsentAction::OPT_OUT->value;
        $status = ConsentStatusSql::status('ev');
        $rejectedAt = ConsentStatusSql::providerRejectedAt('e.contact_id', 'e.email');

        return $this->runQuery(fn () => collect($this->db->select("
            SELECT e.id, e.email, consent.status AS consent_status,
                   preference.action AS preference_action, preference.occurred_at AS preference_at,
                   ($rejectedAt) AS provider_rejected_at
            FROM crm_contact_emails e
            LEFT JOIN LATERAL (
                SELECT $status AS status
                FROM crm_consent_events ev
                WHERE ev.contact_id = e.contact_id AND ev.email = e.email
            ) consent ON true
            LEFT JOIN LATERAL (
                SELECT ev.action, ev.occurred_at
                FROM crm_consent_events ev
                WHERE ev.contact_id = e.contact_id AND ev.email = e.email AND ev.action IN ('$optIn', '$optOut')
                ORDER BY ev.occurred_at DESC, ev.id DESC
                LIMIT 1
            ) preference ON true
            WHERE e.contact_id = ?
            ORDER BY e.id
        ", [$contactId]))->each(static function (object $row): void {
            $row->consent_action = self::CONSENT_ACTION_BY_STATUS[$row->consent_status] ?? null;
            unset($row->consent_status);
        }));
    }

    public function findConsentHistory(int $contactId, int $page, int $perPage): LengthAwarePaginator
    {
        $recipientSources = [
            CrmConsentSource::UNSUBSCRIBE_LINK->value,
            CrmConsentSource::CAMPAIGN->value,
            CrmConsentSource::CONTACT->value,
        ];

        $emailEvents = $this->db->table('crm_consent_events AS ev')
            ->leftJoin('orders AS o', static fn ($join) => $join
                ->on('o.id', '=', 'ev.source_id')
                ->where('ev.source_type', CrmConsentSource::ORDER->value))
            ->leftJoin('crm_imports AS i', static fn ($join) => $join
                ->on('i.id', '=', 'ev.source_id')
                ->where('ev.source_type', CrmConsentSource::IMPORT->value))
            ->leftJoin('crm_campaign_recipients AS r', static fn ($join) => $join
                ->on('r.id', '=', 'ev.source_id')
                ->whereIn('ev.source_type', $recipientSources))
            ->leftJoin('crm_campaigns AS c', 'c.id', '=', 'r.campaign_id')
            ->leftJoin('crm_pending_subscriptions AS ps', static fn ($join) => $join
                ->on('ps.id', '=', 'ev.source_id')
                ->where('ev.source_type', CrmConsentSource::FORM->value))
            ->leftJoin('crm_signup_forms AS sf', 'sf.id', '=', 'ps.form_id')
            ->leftJoin('users AS u', 'u.id', '=', 'ev.user_id')
            ->where('ev.contact_id', $contactId)
            ->select([
                'ev.id',
                $this->db->raw("'".CrmMarketingChannel::EMAIL->value."' AS channel"),
                'ev.action',
                'ev.source_type',
                'ev.email',
                $this->db->raw('NULL AS phone'),
                'ev.occurred_at',
                'o.id AS order_id',
                'o.event_id AS order_event_id',
                'i.file_name AS import_file_name',
                'c.name AS campaign_name',
                'sf.name AS form_name',
                'u.first_name AS user_first_name',
                'u.last_name AS user_last_name',
            ]);

        $smsEvents = $this->db->table('crm_sms_consent_events AS sev')
            ->leftJoin('orders AS so', static fn ($join) => $join
                ->on('so.id', '=', 'sev.source_id')
                ->where('sev.source_type', CrmConsentSource::ORDER->value))
            ->leftJoin('users AS su', 'su.id', '=', 'sev.user_id')
            ->where('sev.contact_id', $contactId)
            ->select([
                'sev.id',
                $this->db->raw("'".CrmMarketingChannel::SMS->value."' AS channel"),
                'sev.action',
                'sev.source_type',
                'sev.email',
                'sev.phone',
                'sev.occurred_at',
                'so.id AS order_id',
                'so.event_id AS order_event_id',
                $this->db->raw('NULL AS import_file_name'),
                $this->db->raw('NULL AS campaign_name'),
                $this->db->raw('NULL AS form_name'),
                'su.first_name AS user_first_name',
                'su.last_name AS user_last_name',
            ]);

        return $this->runQuery(fn () => $this->db->query()
            ->fromSub($emailEvents->unionAll($smsEvents), 'history')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 100), page: $page));
    }

    public function findObservedNames(int $contactId): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_activities')
            ->where('contact_id', $contactId)
            ->whereRaw("nullif(trim(concat_ws(' ', first_name, last_name)), '') IS NOT NULL")
            ->groupByRaw("lower(trim(concat_ws(' ', first_name, last_name))), coalesce(role, 'SELF')")
            ->orderByRaw('count(*) DESC')
            ->limit(25)
            ->selectRaw("
                min(trim(first_name)) AS first_name,
                min(trim(last_name)) AS last_name,
                coalesce(role, 'SELF') AS role,
                count(*) AS seen_count
            ")
            ->get());
    }

    public function countNoShows(int $contactId, CarbonInterface $now): int
    {
        return (int) $this->runQuery(fn () => $this->db->table('crm_contacts')
            ->where('id', $contactId)
            ->selectRaw('('.SegmentQueryCompiler::noShowCountSql().') AS no_show_count', [
                CrmActivityType::TICKET_HELD->value,
                $now->toDateTimeString(),
                CrmActivityType::CHECKED_IN->value,
                CrmActivityType::CHECKED_IN->value,
            ])
            ->value('no_show_count'));
    }

    public function createWithEmail(array $attributes): CrmContactDomainObject
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($attributes) {
            $this->lockOrganizer($attributes[CrmContactDomainObjectAbstract::ORGANIZER_ID]);

            $contactId = $this->db->table('crm_contacts')->insertGetId([
                ...$attributes,
                CrmContactDomainObjectAbstract::CREATED_AT => now(),
                CrmContactDomainObjectAbstract::UPDATED_AT => now(),
            ]);

            $this->db->table('crm_contact_emails')->insert([
                'organizer_id' => $attributes[CrmContactDomainObjectAbstract::ORGANIZER_ID],
                'contact_id' => $contactId,
                'email' => $attributes[CrmContactDomainObjectAbstract::EMAIL],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->handleSingleResult($this->model->newQuery()->findOrFail($contactId));
        }));
    }

    public function findContactEmailId(int $organizerId, int $contactId, string $email): ?int
    {
        $id = $this->runQuery(fn () => $this->db->table('crm_contact_emails')
            ->where('organizer_id', $organizerId)
            ->where('contact_id', $contactId)
            ->where('email', $email)
            ->value('id'));

        return $id === null ? null : (int) $id;
    }

    public function attachTags(int $organizerId, int $contactId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $this->runQuery(fn () => $this->db->insert('
            INSERT INTO crm_contact_tags (contact_id, tag_id, created_at)
            SELECT ?, id, now() FROM crm_tags WHERE organizer_id = ? AND id = ANY(CAST(? AS bigint[]))
            ON CONFLICT (contact_id, tag_id) DO NOTHING
        ', [$contactId, $organizerId, '{'.implode(',', array_map('intval', $tagIds)).'}']));
    }

    public function syncTags(int $organizerId, int $contactId, array $tagIds): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $contactId, $tagIds): void {
            $this->lockOrganizer($organizerId);

            if (! $this->db->table('crm_contacts')->where('organizer_id', $organizerId)->where('id', $contactId)->exists()) {
                return;
            }

            $this->db->table('crm_contact_tags')
                ->where('contact_id', $contactId)
                ->whereNotIn('tag_id', $tagIds)
                ->delete();

            $this->db->table('crm_contact_tags')->insertOrIgnore(array_map(static fn (int $tagId) => [
                'contact_id' => $contactId,
                'tag_id' => $tagId,
                'created_at' => now(),
            ], $tagIds));
        }));
    }

    public function snapshotForBulkOperation(int $operationId, int $organizerId, ?array $contactIds, ?string $query, ?CompiledSegmentDTO $segment): int
    {
        return $this->runQuery(function () use ($operationId, $organizerId, $contactIds, $query, $segment): int {
            $select = $this->db->table('crm_contacts')
                ->selectRaw('CAST(? AS bigint), id', [$operationId])
                ->where('organizer_id', $organizerId)
                ->when($contactIds !== null, static fn (QueryBuilder $builder) => $builder->whereIn('id', $contactIds))
                ->when(! empty($query), fn (QueryBuilder $builder) => $builder->whereIn('id', $this->contactIdsMatching($organizerId, (string) $query)))
                ->when($segment !== null, static fn (QueryBuilder $builder) => $builder->whereRaw($segment->sql, $segment->bindings));

            return $this->db->table('crm_bulk_operation_contacts')->insertUsing(['operation_id', 'contact_id'], $select);
        });
    }

    public function recordOptOuts(int $organizerId, array $contactIds, ?int $userId): array
    {
        if ($contactIds === []) {
            return [];
        }

        return $this->runQuery(fn () => array_map(static fn (object $row) => (int) $row->contact_id, $this->db->select('
            INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, user_id, occurred_at)
            SELECT organizer_id, id, email, ?, ?, ?, now()
            FROM crm_contacts
            WHERE organizer_id = ? AND id = ANY(CAST(? AS bigint[])) AND email_marketing_status NOT IN (?, ?)
              AND coalesce((
                  SELECT latest.action
                  FROM crm_consent_events latest
                  WHERE latest.contact_id = crm_contacts.id AND latest.action IN (?, ?)
                  ORDER BY latest.occurred_at DESC, latest.id DESC
                  LIMIT 1
              ), ?) <> ?
            RETURNING contact_id
        ', [
            CrmConsentAction::OPT_OUT->value,
            CrmConsentSource::MANUAL->value,
            $userId,
            $organizerId,
            '{'.implode(',', array_map('intval', $contactIds)).'}',
            CrmEmailMarketingStatus::UNSUBSCRIBED->value,
            CrmEmailMarketingStatus::COMPLAINED->value,
            CrmConsentAction::OPT_IN->value,
            CrmConsentAction::OPT_OUT->value,
            CrmConsentAction::OPT_IN->value,
            CrmConsentAction::OPT_OUT->value,
        ])));
    }

    public function importContacts(
        int $accountId,
        int $organizerId,
        int $importId,
        ?int $userId,
        array $rows,
        bool $consentAttested,
        ?int $tagId,
        string $deletedContactSalt,
    ): ImportChunkResultDTO {
        return $this->runQuery(fn () => $this->db->transaction(function () use (
            $accountId,
            $organizerId,
            $importId,
            $userId,
            $rows,
            $consentAttested,
            $tagId,
            $deletedContactSalt,
        ): ImportChunkResultDTO {
            $this->lockOrganizer($organizerId);

            $now = now();
            $emails = array_column($rows, 'email');
            $deleted = $this->findTombstonesByEmail($organizerId, $emails, $deletedContactSalt);
            $existing = $this->db->table('crm_contact_emails')
                ->where('organizer_id', $organizerId)
                ->whereIn('email', $emails)
                ->pluck('contact_id', 'email')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $newRows = array_values(array_filter(
                $rows,
                static fn (array $row) => ! array_key_exists($row['email'], $deleted) && ! isset($existing[$row['email']]),
            ));

            $createdIds = $this->insertImportedContacts($accountId, $organizerId, $importId, $newRows, $now);
            $existingIds = array_intersect_key($existing, array_diff_key(array_flip($emails), $deleted));
            $contactIds = array_values(array_unique([...array_values($existingIds), ...array_values($createdIds)]));

            if ($tagId !== null && $contactIds !== []) {
                $this->db->insert('
                    INSERT INTO crm_contact_tags (contact_id, tag_id, created_at)
                    SELECT c.id, t.id, ?
                    FROM crm_tags t
                    JOIN crm_contacts c ON c.organizer_id = t.organizer_id AND c.id = ANY(CAST(? AS bigint[]))
                    WHERE t.organizer_id = ? AND t.id = ?
                    ON CONFLICT (contact_id, tag_id) DO NOTHING
                ', [$now, '{'.implode(',', $contactIds).'}', $organizerId, $tagId]);
            }

            if ($consentAttested && $createdIds !== []) {
                $this->db->table('crm_consent_events')->insertOrIgnore(array_map(static fn (int $contactId, string $email) => [
                    'organizer_id' => $organizerId,
                    'contact_id' => $contactId,
                    'email' => $email,
                    'action' => CrmConsentAction::OPT_IN->value,
                    'source_type' => CrmConsentSource::IMPORT->value,
                    'source_id' => $importId,
                    'user_id' => $userId,
                    'occurred_at' => $now,
                ], array_values($createdIds), array_keys($createdIds)));
            }

            return new ImportChunkResultDTO(
                created: count($createdIds),
                updated: count($existingIds),
                previously_deleted: count($deleted),
                contact_ids: $contactIds,
            );
        }));
    }

    /**
     * @param  array<int, array{email: string, first_name: string|null, last_name: string|null}>  $rows
     * @return array<string, int>
     */
    private function insertImportedContacts(int $accountId, int $organizerId, int $importId, array $rows, CarbonInterface $now): array
    {
        if ($rows === []) {
            return [];
        }

        $values = [];
        $bindings = [];
        foreach ($rows as $row) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($bindings, $accountId, $organizerId, $row['email'], $row['first_name'], $row['last_name'], CrmContactSource::IMPORT->value, $now, $now, $now);
        }

        $createdIds = collect($this->db->select('
            INSERT INTO crm_contacts (account_id, organizer_id, email, first_name, last_name, source, first_seen_at, created_at, updated_at)
            VALUES '.implode(', ', $values).'
            ON CONFLICT (organizer_id, email) DO NOTHING
            RETURNING id, email
        ', $bindings))->mapWithKeys(static fn (object $row) => [$row->email => (int) $row->id])->all();

        $this->db->table('crm_contact_emails')->insert(array_map(static fn (int $contactId, string $email) => [
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($createdIds), array_keys($createdIds)));

        $this->db->table('crm_contact_activities')->insert(array_map(static fn (int $contactId, string $email) => [
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'type' => CrmActivityType::IMPORTED->value,
            'source_type' => CrmActivitySource::IMPORT->value,
            'source_id' => $importId,
            'email' => $email,
            'occurred_at' => $now,
        ], array_values($createdIds), array_keys($createdIds)));

        return $createdIds;
    }

    public function importSuppressions(
        int $accountId,
        int $organizerId,
        int $importId,
        ?int $userId,
        array $rows,
        string $deletedContactSalt,
    ): ImportChunkResultDTO {
        return $this->runQuery(fn () => $this->db->transaction(function () use (
            $accountId,
            $organizerId,
            $importId,
            $userId,
            $rows,
            $deletedContactSalt,
        ): ImportChunkResultDTO {
            $this->lockOrganizer($organizerId);

            $now = now();
            $rowsByEmail = array_column($rows, null, 'email');
            $tombstoned = $this->upgradeTombstoneSuppressions($organizerId, $rowsByEmail, $deletedContactSalt);
            $existing = $this->db->table('crm_contact_emails')
                ->where('organizer_id', $organizerId)
                ->whereIn('email', array_keys($rowsByEmail))
                ->pluck('contact_id', 'email')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $existing = array_diff_key($existing, $tombstoned);
            $current = $this->suppressionsByEmail(array_values(array_unique($existing)));

            $toSuppress = array_filter(
                $existing,
                static fn (int $contactId, string $email) => ! in_array(
                    $current[$email] ?? null,
                    [CrmEmailMarketingStatus::COMPLAINED->value, $rowsByEmail[$email]['reason']->value],
                    true,
                ),
                ARRAY_FILTER_USE_BOTH,
            );

            $newRows = array_values(array_diff_key($rowsByEmail, $existing, $tombstoned));
            $createdIds = $this->insertImportedContacts($accountId, $organizerId, $importId, $newRows, $now);
            $targets = $toSuppress + $createdIds;

            $this->db->table('crm_consent_events')->insertOrIgnore(array_map(static fn (int $contactId, string $email) => [
                'organizer_id' => $organizerId,
                'contact_id' => $contactId,
                'email' => $email,
                'action' => self::suppressionAction($rowsByEmail[$email]['reason'])->value,
                'source_type' => CrmConsentSource::IMPORT->value,
                'source_id' => $importId,
                'user_id' => $userId,
                'occurred_at' => $now,
            ], array_values($targets), array_keys($targets)));

            $bouncedOnSecondaryOnly = $this->contactsBouncedOnlyOnSecondaryEmails($toSuppress, $rowsByEmail);

            return new ImportChunkResultDTO(
                created: count($createdIds),
                updated: count(array_unique($toSuppress)) - count($bouncedOnSecondaryOnly),
                previously_deleted: count($tombstoned),
                contact_ids: array_values(array_unique(array_values($targets))),
                already_suppressed: count(array_unique($existing)) - count(array_unique($toSuppress)),
                bounced_secondary_address: count($bouncedOnSecondaryOnly),
            );
        }));
    }

    /**
     * @param  array<string, int>  $contactIdsByEmail
     * @param  array<string, array{reason: CrmEmailMarketingStatus}>  $rowsByEmail
     * @return int[]
     */
    private function contactsBouncedOnlyOnSecondaryEmails(array $contactIdsByEmail, array $rowsByEmail): array
    {
        $bounced = array_filter(
            $contactIdsByEmail,
            static fn (string $email) => $rowsByEmail[$email]['reason'] === CrmEmailMarketingStatus::BOUNCED,
            ARRAY_FILTER_USE_KEY,
        );

        if ($bounced === []) {
            return [];
        }

        $primaryEmails = $this->db->table('crm_contacts')
            ->whereIn('id', array_values(array_unique($bounced)))
            ->pluck('email', 'id')
            ->all();

        $bouncedOnPrimary = array_filter(
            $bounced,
            static fn (int $contactId, string $email) => $primaryEmails[$contactId] === $email,
            ARRAY_FILTER_USE_BOTH,
        );

        return array_values(array_unique(array_diff($bounced, $bouncedOnPrimary, array_diff_key($contactIdsByEmail, $bounced))));
    }

    /**
     * @param  array<string, array{reason: CrmEmailMarketingStatus}>  $rowsByEmail
     * @return array<string, true>
     */
    private function upgradeTombstoneSuppressions(int $organizerId, array $rowsByEmail, string $salt): array
    {
        $tombstones = $this->findTombstonesByEmail($organizerId, array_keys($rowsByEmail), $salt);

        foreach ($tombstones as $email => $suppression) {
            $reason = $rowsByEmail[$email]['reason']->value;

            if (self::suppressionStrength($reason) > self::suppressionStrength($suppression)) {
                $this->db->table('crm_deleted_contacts')
                    ->where('organizer_id', $organizerId)
                    ->where('email_hash', DeletedContactHasher::hashWithSalt($salt, $email))
                    ->update(['suppression' => $reason]);
            }
        }

        return array_fill_keys(array_keys($tombstones), true);
    }

    private static function suppressionStrength(?string $status): int
    {
        return $status === null ? 0 : CrmEmailMarketingStatus::from($status)->suppressionStrength();
    }

    private static function suppressionAction(CrmEmailMarketingStatus $reason): CrmConsentAction
    {
        return match ($reason) {
            CrmEmailMarketingStatus::COMPLAINED => CrmConsentAction::COMPLAINED,
            CrmEmailMarketingStatus::BOUNCED => CrmConsentAction::BOUNCED,
            default => CrmConsentAction::OPT_OUT,
        };
    }

    public function hasDeletedEmail(int $organizerId, string $emailHash): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_deleted_contacts')
            ->where('organizer_id', $organizerId)
            ->where('email_hash', $emailHash)
            ->exists());
    }

    public function findOrCreateDeletedContactSalt(int $organizerId): string
    {
        return $this->runQuery(function () use ($organizerId): string {
            $salt = fn (): ?string => $this->db->table('crm_deleted_contact_salts')
                ->where(CrmDeletedContactSaltDomainObjectAbstract::ORGANIZER_ID, $organizerId)
                ->value(CrmDeletedContactSaltDomainObjectAbstract::SALT);

            if ($salt() === null) {
                $this->db->table('crm_deleted_contact_salts')->insertOrIgnore([
                    CrmDeletedContactSaltDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                    CrmDeletedContactSaltDomainObjectAbstract::CREATED_AT => now(),
                ]);
            }

            return (string) $salt();
        });
    }

    public function hasUnreviewedImportOptIns(int $organizerId, ?CompiledSegmentDTO $segment): bool
    {
        return $this->runQuery(function () use ($organizerId, $segment): bool {
            $query = $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->where('email_marketing_status', CrmEmailMarketingStatus::SUBSCRIBED->value)
                ->whereNotExists(static fn ($optIns) => $optIns
                    ->selectRaw('1')
                    ->from('crm_consent_events AS ev')
                    ->whereColumn('ev.contact_id', 'crm_contacts.id')
                    ->whereColumn('ev.email', 'crm_contacts.email')
                    ->where('ev.action', CrmConsentAction::OPT_IN->value)
                    ->where(static fn ($reviewed) => $reviewed
                        ->where('ev.source_type', '<>', CrmConsentSource::IMPORT->value)
                        ->orWhereExists(static fn ($approved) => $approved
                            ->selectRaw('1')
                            ->from('crm_campaign_recipients AS r')
                            ->join('crm_campaigns AS rc', 'rc.id', '=', 'r.campaign_id')
                            ->whereColumn('r.contact_id', 'crm_contacts.id')
                            ->whereNotNull('rc.approved_at')
                            ->whereColumn('rc.launched_at', '>=', 'ev.occurred_at'))));

            if ($segment !== null) {
                $query->whereRaw($segment->sql, $segment->bindings);
            }

            return $query->exists();
        });
    }

    public function wasLastOptedOutByLink(int $contactId, int $recipientId): bool
    {
        $latest = $this->runQuery(fn () => $this->db->table('crm_consent_events')
            ->where('contact_id', $contactId)
            ->where('action', CrmConsentAction::OPT_OUT->value)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first(['source_type', 'source_id']));

        return $latest !== null
            && $latest->source_type === CrmConsentSource::UNSUBSCRIBE_LINK->value
            && (int) $latest->source_id === $recipientId;
    }

    public function findExistingIds(int $organizerId, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->db->table('crm_contacts')
            ->where('organizer_id', $organizerId)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function forgetDeletedEmail(int $organizerId, string $emailHash): DeletedContactConsentDTO
    {
        return $this->runQuery(function () use ($organizerId, $emailHash): DeletedContactConsentDTO {
            $deleted = $this->db->selectOne(
                'DELETE FROM crm_deleted_contacts WHERE organizer_id = ? AND email_hash = ? RETURNING suppression, sms_opted_out',
                [$organizerId, $emailHash],
            );

            return new DeletedContactConsentDTO(
                suppression: CrmEmailMarketingStatus::tryFrom((string) $deleted?->suppression),
                sms_opted_out: (bool) $deleted?->sms_opted_out,
            );
        });
    }

    public function insertConsentEvent(
        int $organizerId,
        int $contactId,
        string $email,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId,
        ?int $userId,
    ): void {
        $this->runQuery(fn () => $this->db->table('crm_consent_events')->insertOrIgnore([
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'action' => $action->value,
            'source_type' => $source->value,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'occurred_at' => now(),
        ]));
    }

    public function insertSmsConsentEvent(
        int $organizerId,
        int $contactId,
        string $email,
        string $phone,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId,
        ?int $userId,
    ): void {
        $this->runQuery(fn () => $this->db->insert('
            INSERT INTO crm_sms_consent_events (organizer_id, contact_id, email, phone, action, source_type, source_id, user_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (contact_id, action, source_type, source_id, email) WHERE source_id IS NOT NULL
            DO UPDATE SET occurred_at = EXCLUDED.occurred_at, phone = EXCLUDED.phone
        ', [
            $organizerId,
            $contactId,
            $email,
            $phone,
            $action->value,
            $source->value,
            $sourceId,
            $userId,
            now()->toDateTimeString(),
        ]));
    }

    public function recordRecipientConsent(
        int $organizerId,
        int $contactId,
        string $email,
        CrmConsentAction $action,
        CrmConsentSource $source,
        int $recipientId,
    ): void {
        $this->runQuery(fn () => $this->db->insert('
            INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, source_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (contact_id, action, source_type, source_id, email) WHERE source_id IS NOT NULL
            DO UPDATE SET occurred_at = EXCLUDED.occurred_at
        ', [
            $organizerId,
            $contactId,
            $email,
            $action->value,
            $source->value,
            $recipientId,
            now()->toDateTimeString(),
        ]));
    }

    public function countSegment(int $organizerId, ?CompiledSegmentDTO $segment): object
    {
        return $this->runQuery(function () use ($organizerId, $segment) {
            $query = $this->db->table('crm_contacts')->where('organizer_id', $organizerId);

            if ($segment !== null) {
                $query->whereRaw($segment->sql, $segment->bindings);
            }

            return $query
                ->selectRaw('count(*) AS contacts_count, count(*) FILTER (WHERE email_marketing_status = ?) AS mailable_count, count(DISTINCT phone) FILTER (WHERE sms_marketing_status = ?) AS textable_count', [
                    CrmEmailMarketingStatus::SUBSCRIBED->value,
                    CrmSmsMarketingStatus::SUBSCRIBED->value,
                ])
                ->first();
        });
    }

    public function countTextable(int $organizerId, ?CompiledSegmentDTO $segment, array $countries): int
    {
        return array_sum($this->countTextableByCountry($organizerId, $segment, $countries));
    }

    public function countTextableByCountry(int $organizerId, ?CompiledSegmentDTO $segment, array $countries): array
    {
        if ($countries === []) {
            return [];
        }

        return $this->runQuery(function () use ($organizerId, $segment, $countries): array {
            $query = $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->where('sms_marketing_status', CrmSmsMarketingStatus::SUBSCRIBED->value)
                ->whereNotNull('phone')
                ->whereIn('phone_country', $countries);

            if ($segment !== null) {
                $query->whereRaw($segment->sql, $segment->bindings);
            }

            return $query
                ->groupBy('phone_country')
                ->selectRaw('phone_country, count(DISTINCT phone) AS recipients')
                ->pluck('recipients', 'phone_country')
                ->map(static fn ($recipients) => (int) $recipients)
                ->all();
        });
    }

    public function deleteContacts(int $organizerId, array $contactIds, string $emailHashSalt): array
    {
        if ($contactIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $contactIds, $emailHashSalt): array {
            $this->lockOrganizer($organizerId);

            $lockedIds = $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->whereIn('id', $contactIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($lockedIds === []) {
                return [];
            }

            $contactIdsByEmail = $this->db->table('crm_contact_emails')
                ->where('organizer_id', $organizerId)
                ->whereIn('contact_id', $lockedIds)
                ->pluck('contact_id', 'email')
                ->all();
            $emails = array_map('strval', array_keys($contactIdsByEmail));

            $suppressions = $this->suppressionsByEmail($lockedIds);
            $contactWideSuppressions = $this->suppressionsOnAnyAddress($lockedIds);
            $optedOutOfTexts = $this->db->table('crm_contacts')
                ->whereIn('id', $lockedIds)
                ->where('sms_marketing_status', CrmSmsMarketingStatus::UNSUBSCRIBED->value)
                ->pluck('id')
                ->flip();
            $hashes = [];

            foreach ($emails as $email) {
                $hashes[$email] = DeletedContactHasher::hashWithSalt($emailHashSalt, $email);
            }

            $this->db->table('crm_campaign_recipients')
                ->where('organizer_id', $organizerId)
                ->whereIn('contact_id', $lockedIds)
                ->whereNotNull('phone')
                ->update(['phone' => null]);

            foreach (array_chunk($emails, 500) as $emailChunk) {
                $this->db->table('crm_deleted_contacts')->upsert(array_map(static fn (string $email) => [
                    'organizer_id' => $organizerId,
                    'email_hash' => $hashes[$email],
                    'suppression' => self::strongerSuppression($suppressions[$email] ?? null, $contactWideSuppressions[$contactIdsByEmail[$email]] ?? null),
                    'sms_opted_out' => $optedOutOfTexts->has($contactIdsByEmail[$email]),
                    'created_at' => now(),
                ], $emailChunk), ['organizer_id', 'email_hash'], ['suppression', 'sms_opted_out']);

                $values = implode(', ', array_fill(0, count($emailChunk), '(?, ?)'));
                $bindings = [];

                foreach ($emailChunk as $email) {
                    $bindings[] = $email;
                    $bindings[] = DeletedContactHasher::recipientPlaceholder($hashes[$email]);
                }

                $this->db->update("
                    UPDATE crm_campaign_recipients r
                    SET contact_id = NULL, email = v.replacement
                    FROM (VALUES {$values}) AS v(email, replacement)
                    WHERE r.organizer_id = ? AND r.email = v.email
                ", [...$bindings, $organizerId]);
            }

            $this->db->table('crm_pending_subscriptions')
                ->where('organizer_id', $organizerId)
                ->whereIn('email', $emails)
                ->delete();

            $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->whereIn('id', $lockedIds)
                ->delete();

            return $lockedIds;
        }));
    }

    /**
     * @param  int[]  $contactIds
     * @return array<string, string>
     */
    private function suppressionsByEmail(array $contactIds): array
    {
        $suppressed = array_map(static fn (CrmEmailMarketingStatus $status) => $status->value, CrmSuppressionRepository::SUPPRESSED_STATUSES);

        return collect($this->db->select('
            SELECT ev.email, '.ConsentStatusSql::status('ev').' AS suppression
            FROM crm_consent_events ev
            WHERE ev.contact_id = ANY(CAST(? AS bigint[]))
            GROUP BY ev.email
        ', ['{'.implode(',', array_map('intval', $contactIds)).'}']))
            ->whereIn('suppression', $suppressed)
            ->pluck('suppression', 'email')
            ->all();
    }

    /**
     * @param  int[]  $contactIds
     * @return array<int, string>
     */
    private function suppressionsOnAnyAddress(array $contactIds): array
    {
        return collect($this->db->select('
            SELECT c.id, '.ConsentStatusSql::suppressionOnAnyAddress('ev', 'ev.email = c.email').' AS suppression
            FROM crm_contacts c
            JOIN crm_consent_events ev ON ev.contact_id = c.id
            WHERE c.id = ANY(CAST(? AS bigint[]))
            GROUP BY c.id
        ', ['{'.implode(',', array_map('intval', $contactIds)).'}']))
            ->whereNotNull('suppression')
            ->pluck('suppression', 'id')
            ->all();
    }

    private static function tombstoneOfRecipient(): string
    {
        return 'r.id = ? AND r.organizer_id = ? AND r.contact_id IS NULL AND d.organizer_id = r.organizer_id'
            .' AND '.DeletedContactHasher::isRecipientPlaceholderSql('r.email')
            .' AND d.email_hash = '.DeletedContactHasher::hashFromRecipientPlaceholderSql('r.email');
    }

    private static function strongerSuppression(?string $first, ?string $second): ?string
    {
        return self::suppressionStrength($second) > self::suppressionStrength($first) ? $second : $first;
    }

    public function strengthenDeletedRecipientSuppression(int $organizerId, int $recipientId, CrmEmailMarketingStatus $suppression): void
    {
        $weaker = array_values(array_map(
            static fn (CrmEmailMarketingStatus $status) => $status->value,
            array_filter(
                CrmSuppressionRepository::SUPPRESSED_STATUSES,
                static fn (CrmEmailMarketingStatus $status) => $status->suppressionStrength() < $suppression->suppressionStrength(),
            ),
        ));

        $this->runQuery(fn () => $this->db->update('
            UPDATE crm_deleted_contacts d
            SET suppression = ?
            FROM crm_campaign_recipients r
            WHERE '.self::tombstoneOfRecipient().'
              AND (d.suppression IS NULL OR d.suppression = ANY(CAST(? AS text[])))
        ', [$suppression->value, $recipientId, $organizerId, '{'.implode(',', $weaker).'}']));
    }

    public function markDeletedRecipientOptedOutOfTexts(int $organizerId, int $recipientId): void
    {
        $this->runQuery(fn () => $this->db->update('
            UPDATE crm_deleted_contacts d
            SET sms_opted_out = true
            FROM crm_campaign_recipients r
            WHERE '.self::tombstoneOfRecipient().'
        ', [$recipientId, $organizerId]));
    }

    public function findDeletedRecipientTombstone(int $organizerId, int $recipientId): ?object
    {
        return $this->runQuery(fn () => $this->db->selectOne('
            SELECT d.suppression, d.sms_opted_out
            FROM crm_deleted_contacts d
            JOIN crm_campaign_recipients r ON '.self::tombstoneOfRecipient().'
        ', [$recipientId, $organizerId]));
    }

    public function relinkDeletedRecipients(int $organizerId, string $email, string $emailHash): void
    {
        $this->runQuery(function () use ($organizerId, $email, $emailHash): void {
            $relinked = $this->db->select('
                UPDATE crm_campaign_recipients r
                SET contact_id = ce.contact_id, email = ce.email
                FROM crm_contact_emails ce
                WHERE ce.organizer_id = ? AND ce.email = ?
                  AND r.organizer_id = ce.organizer_id AND r.email = ?
                RETURNING r.id
            ', [$organizerId, $email, DeletedContactHasher::recipientPlaceholder($emailHash)]);

            if ($relinked === []) {
                return;
            }

            $this->db->insert('
                INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, source_id, occurred_at)
                SELECT r.organizer_id, r.contact_id, r.email, ?, ?, r.id, r.updated_at
                FROM crm_campaign_recipients r
                WHERE r.id = ANY(CAST(? AS bigint[]))
                  AND r.failure_reason = ?
                  AND r.phone_country IS NULL
                  AND EXISTS (
                      SELECT 1 FROM crm_consent_events restored
                      WHERE restored.contact_id = r.contact_id AND restored.email = r.email AND restored.action = ?
                  )
                ON CONFLICT DO NOTHING
            ', [
                CrmConsentAction::BOUNCED->value,
                CrmConsentSource::CAMPAIGN->value,
                '{'.implode(',', array_map(static fn (object $row) => (int) $row->id, $relinked)).'}',
                CrmRecipientFailureReason::PROVIDER_SUPPRESSED->value,
                CrmConsentAction::BOUNCED->value,
            ]);
        });
    }

    public function mergeContacts(int $organizerId, int $targetContactId, int $sourceContactId): bool
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $targetContactId, $sourceContactId): bool {
            $this->lockOrganizer($organizerId);

            $locked = $this->db->select(
                'SELECT id FROM crm_contacts WHERE organizer_id = ? AND id IN (?, ?) ORDER BY id FOR UPDATE',
                [$organizerId, $targetContactId, $sourceContactId],
            );

            if (count($locked) !== 2) {
                return false;
            }

            $this->db->delete('
                DELETE FROM crm_contact_activities source
                USING crm_contact_activities target
                WHERE source.contact_id = ? AND target.contact_id = ?
                  AND source.type = target.type AND source.source_type = target.source_type AND source.source_id = target.source_id
            ', [$sourceContactId, $targetContactId]);

            $this->db->delete('
                DELETE FROM crm_contact_tags source
                USING crm_contact_tags target
                WHERE source.contact_id = ? AND target.contact_id = ? AND source.tag_id = target.tag_id
            ', [$sourceContactId, $targetContactId]);

            foreach (['crm_consent_events', 'crm_sms_consent_events'] as $consentTable) {
                $this->db->delete("
                    DELETE FROM $consentTable source
                    USING $consentTable target
                    WHERE source.contact_id = ? AND target.contact_id = ?
                      AND source.action = target.action AND source.source_type = target.source_type AND source.source_id = target.source_id
                      AND source.email = target.email
                ", [$sourceContactId, $targetContactId]);
            }

            foreach (['crm_contact_emails', 'crm_contact_activities', 'crm_contact_tags', 'crm_contact_notes', 'crm_consent_events', 'crm_sms_consent_events', 'crm_campaign_recipients'] as $table) {
                $this->db->table($table)->where('contact_id', $sourceContactId)->update(['contact_id' => $targetContactId]);
            }

            $this->copyAutomationMemberships($sourceContactId, $targetContactId);

            $this->db->insert('
                INSERT INTO crm_campaign_audience_contacts (campaign_id, contact_id)
                SELECT campaign_id, ? FROM crm_campaign_audience_contacts WHERE contact_id = ?
                ON CONFLICT (campaign_id, contact_id) DO NOTHING
            ', [$targetContactId, $sourceContactId]);

            $this->db->table('crm_contacts')->where('organizer_id', $organizerId)->where('id', $sourceContactId)->delete();

            return true;
        }));
    }

    public function splitEmail(int $organizerId, int $contactId, int $contactEmailId): ?int
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $contactId, $contactEmailId): ?int {
            $this->lockOrganizer($organizerId);

            $contact = $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->where('id', $contactId)
                ->lockForUpdate()
                ->first();

            $email = $this->db->table('crm_contact_emails')
                ->where('id', $contactEmailId)
                ->where('contact_id', $contactId)
                ->value('email');

            if ($contact === null || $email === null || $email === $contact->email) {
                return null;
            }

            $newContactId = $this->db->table('crm_contacts')->insertGetId([
                'account_id' => $contact->account_id,
                'organizer_id' => $organizerId,
                'email' => $email,
                'locale' => $contact->locale,
                'source' => CrmContactSource::MANUAL->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->db->table('crm_contact_emails')->where('id', $contactEmailId)->update(['contact_id' => $newContactId]);

            foreach (['crm_contact_activities', 'crm_consent_events', 'crm_sms_consent_events', 'crm_campaign_recipients'] as $table) {
                $this->db->table($table)
                    ->where('contact_id', $contactId)
                    ->where('email', $email)
                    ->update(['contact_id' => $newContactId]);
            }

            $this->copyAutomationMemberships($contactId, $newContactId);

            return $newContactId;
        }));
    }

    private function copyAutomationMemberships(int $fromContactId, int $toContactId): void
    {
        $this->db->insert('
            INSERT INTO crm_automation_memberships (automation_id, contact_id)
            SELECT automation_id, ? FROM crm_automation_memberships WHERE contact_id = ?
            ON CONFLICT (automation_id, contact_id) DO NOTHING
        ', [$toContactId, $fromContactId]);
    }

    public function setPrimaryEmail(int $organizerId, int $contactId, int $contactEmailId): bool
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $contactId, $contactEmailId): bool {
            $this->lockOrganizer($organizerId);

            $email = $this->db->table('crm_contact_emails')
                ->where('id', $contactEmailId)
                ->where('organizer_id', $organizerId)
                ->where('contact_id', $contactId)
                ->value('email');

            if ($email === null) {
                return false;
            }

            $this->db->table('crm_contacts')
                ->where('organizer_id', $organizerId)
                ->where('id', $contactId)
                ->update(['email' => $email, 'updated_at' => now()]);

            return true;
        }));
    }
}
