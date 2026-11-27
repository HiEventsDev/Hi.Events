<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionDetailDTO;
use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionImportMatchDTO;
use HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns\FindsTombstones;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSuppressionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentStatusSql;
use HiEvents\Models\CrmContact;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmContactDomainObject>
 */
class CrmSuppressionRepository extends BaseRepository implements CrmSuppressionRepositoryInterface
{
    use FindsTombstones;

    public const SUPPRESSED_STATUSES = [
        CrmEmailMarketingStatus::UNSUBSCRIBED,
        CrmEmailMarketingStatus::BOUNCED,
        CrmEmailMarketingStatus::COMPLAINED,
    ];

    private const LOOKUP_CHUNK = 5000;

    protected function getModel(): string
    {
        return CrmContact::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactDomainObject::class;
    }

    public function findDetailsForContacts(int $organizerId, array $contactIds): Collection
    {
        if ($contactIds === []) {
            return collect();
        }

        $complaintStands = ConsentStatusSql::complaintStands('ev');
        $bounceStands = ConsentStatusSql::bounceStands('ev');
        $providerRejectedAt = ConsentStatusSql::providerRejectedAt('c.id', 'c.email');

        return $this->runQuery(fn () => collect($this->db->select("
            SELECT DISTINCT ON (c.id)
                c.id AS contact_id,
                c.email_marketing_status AS reason,
                ev.occurred_at,
                ev.source_type,
                ev.email,
                camp.name AS campaign_name,
                imp.file_name AS import_file_name,
                nullif(trim(concat_ws(' ', u.first_name, u.last_name)), '') AS user_name,
                coalesce(r.failure_reason = :provider_suppressed, false) AS provider_rejected,
                CASE WHEN c.email_marketing_status = :bounced_reason THEN ($providerRejectedAt) END AS provider_rejected_at
            FROM crm_contacts c
            JOIN crm_consent_events ev ON ev.contact_id = c.id AND (
                (c.email_marketing_status = :complained_status AND $complaintStands)
                OR (c.email_marketing_status = :bounced_status AND ev.email = c.email AND $bounceStands)
                OR (c.email_marketing_status = :unsubscribed_status AND ev.action = :opt_out)
            )
            LEFT JOIN crm_campaign_recipients r ON r.id = ev.source_id AND ev.source_type IN (:unsubscribe_link, :campaign, :contact)
            LEFT JOIN crm_campaigns camp ON camp.id = r.campaign_id
            LEFT JOIN crm_imports imp ON imp.id = ev.source_id AND ev.source_type = :import
            LEFT JOIN users u ON u.id = ev.user_id
            WHERE c.organizer_id = :organizer AND c.id = ANY(CAST(:contacts AS bigint[]))
            ORDER BY c.id, ev.occurred_at DESC, ev.id DESC
        ", [
            'provider_suppressed' => CrmRecipientFailureReason::PROVIDER_SUPPRESSED->value,
            'bounced_reason' => CrmEmailMarketingStatus::BOUNCED->value,
            'complained_status' => CrmEmailMarketingStatus::COMPLAINED->value,
            'bounced_status' => CrmEmailMarketingStatus::BOUNCED->value,
            'unsubscribed_status' => CrmEmailMarketingStatus::UNSUBSCRIBED->value,
            'opt_out' => CrmConsentAction::OPT_OUT->value,
            'unsubscribe_link' => CrmConsentSource::UNSUBSCRIBE_LINK->value,
            'campaign' => CrmConsentSource::CAMPAIGN->value,
            'contact' => CrmConsentSource::CONTACT->value,
            'import' => CrmConsentSource::IMPORT->value,
            'organizer' => $organizerId,
            'contacts' => '{'.implode(',', array_map('intval', $contactIds)).'}',
        ]))->mapWithKeys(static fn (object $row) => [(int) $row->contact_id => new SuppressionDetailDTO(
            contact_id: (int) $row->contact_id,
            reason: $row->reason,
            since: $row->occurred_at,
            source_type: $row->source_type,
            email: $row->email,
            campaign_name: $row->campaign_name,
            import_file_name: $row->import_file_name,
            user_name: $row->user_name,
            provider_rejected: (bool) $row->provider_rejected,
            provider_rejected_at: $row->provider_rejected_at,
        )]));
    }

    public function countByReason(int $organizerId): array
    {
        $counts = $this->runQuery(fn () => $this->db->table('crm_contacts')
            ->where('organizer_id', $organizerId)
            ->whereIn('email_marketing_status', array_map(static fn (CrmEmailMarketingStatus $status) => $status->value, self::SUPPRESSED_STATUSES))
            ->groupBy('email_marketing_status')
            ->selectRaw('email_marketing_status, count(*) AS total')
            ->pluck('total', 'email_marketing_status')
            ->all());

        return collect(self::SUPPRESSED_STATUSES)
            ->mapWithKeys(static fn (CrmEmailMarketingStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    public function countSuppressedTombstones(int $organizerId): int
    {
        return $this->runQuery(fn () => $this->db->table('crm_deleted_contacts')
            ->where('organizer_id', $organizerId)
            ->whereNotNull('suppression')
            ->count());
    }

    public function findContactsByEmail(int $organizerId, array $emails): Collection
    {
        if ($emails === []) {
            return collect();
        }

        $contactIdsByEmail = $this->runQuery(fn () => $this->db->table('crm_contact_emails')
            ->where('organizer_id', $organizerId)
            ->whereIn('email', $emails)
            ->pluck('contact_id', 'email'));

        if ($contactIdsByEmail->isEmpty()) {
            return collect();
        }

        $contacts = $this->findWhereIn(
            CrmContactDomainObjectAbstract::ID,
            $contactIdsByEmail->unique()->values()->all(),
            [CrmContactDomainObjectAbstract::ORGANIZER_ID => $organizerId],
        )->keyBy(static fn (CrmContactDomainObject $contact) => $contact->getId());

        return $contactIdsByEmail
            ->map(static fn ($contactId) => $contacts->get((int) $contactId))
            ->filter();
    }

    public function findTombstoneSuppressions(int $organizerId, array $emails, string $salt): array
    {
        return $this->runQuery(fn () => $this->findTombstonesByEmail($organizerId, $emails, $salt));
    }

    public function countMatchingContacts(int $organizerId, array $emails, array $bouncedEmails): SuppressionImportMatchDTO
    {
        $statuses = [];
        $suppressible = [];
        $matchedEmails = 0;
        $isBounced = array_fill_keys($bouncedEmails, true);

        foreach (array_chunk($emails, self::LOOKUP_CHUNK) as $chunk) {
            $rows = $this->runQuery(fn () => $this->db->table('crm_contact_emails AS e')
                ->join('crm_contacts AS c', 'c.id', '=', 'e.contact_id')
                ->where('e.organizer_id', $organizerId)
                ->whereIn('e.email', $chunk)
                ->get(['c.id', 'c.email_marketing_status', 'e.email', 'c.email AS primary_email']));

            $matchedEmails += $rows->count();
            $statuses += $rows->pluck('email_marketing_status', 'id')->all();
            $suppressible += $rows
                ->filter(static fn (object $row) => ! isset($isBounced[$row->email]) || $row->email === $row->primary_email)
                ->pluck('id', 'id')
                ->all();
        }

        return new SuppressionImportMatchDTO(
            emails: $matchedEmails,
            contacts: count($statuses),
            subscribed: count(array_filter(
                array_intersect_key($statuses, $suppressible),
                static fn (string $status) => $status === CrmEmailMarketingStatus::SUBSCRIBED->value,
            )),
        );
    }
}
