<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmTagDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Models\CrmTag;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmTagDomainObject>
 */
class CrmTagRepository extends BaseRepository implements CrmTagRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmTag::class;
    }

    public function getDomainObject(): string
    {
        return CrmTagDomainObject::class;
    }

    public function findByOrganizerIdWithCounts(int $organizerId): Collection
    {
        return $this->runQuery(function () use ($organizerId) {
            $counts = $this->db->table('crm_contact_tags')
                ->join('crm_tags', 'crm_tags.id', '=', 'crm_contact_tags.tag_id')
                ->where('crm_tags.organizer_id', $organizerId)
                ->groupBy('crm_contact_tags.tag_id')
                ->selectRaw('crm_contact_tags.tag_id, count(*) AS contacts_count')
                ->pluck('contacts_count', 'tag_id');

            return $this->handleResults($this->model->where('organizer_id', $organizerId)->orderBy('name')->get())
                ->each(static fn (CrmTagDomainObject $tag) => $tag->setContactsCount((int) ($counts[$tag->getId()] ?? 0)));
        });
    }

    public function findIdByName(int $organizerId, string $name): ?int
    {
        $tagId = $this->runQuery(fn () => $this->db->table('crm_tags')
            ->where('organizer_id', $organizerId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->value('id'));

        return $tagId === null ? null : (int) $tagId;
    }

    public function nameExists(int $organizerId, string $name, ?int $exceptTagId = null): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_tags')
            ->where('organizer_id', $organizerId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptTagId !== null, static fn ($query) => $query->where('id', '!=', $exceptTagId))
            ->exists());
    }

    public function countForOrganizer(int $organizerId, array $tagIds): int
    {
        return $this->runQuery(fn () => $this->db->table('crm_tags')
            ->where('organizer_id', $organizerId)
            ->whereIn('id', $tagIds)
            ->count());
    }

    public function findRuleUsageNames(int $organizerId, int $tagId): array
    {
        $usesTag = "jsonb_path_exists(%s, '$.rules[*] ? (@.field == \"tag\" && @.value[*] == \$id)', jsonb_build_object('id', CAST(? AS bigint)))";
        $statuses = CrmCampaignStatus::valuesOf(CrmCampaignStatus::resolvingAudienceLater());

        return $this->runQuery(fn () => array_map(static fn (object $row) => (string) $row->label, $this->db->select('
            SELECT name AS label FROM crm_segments WHERE organizer_id = ? AND '.sprintf($usesTag, 'definition').'
            UNION ALL
            SELECT name FROM crm_campaigns WHERE organizer_id = ? AND status IN ('.implode(', ', array_fill(0, count($statuses), '?')).') AND '.sprintf($usesTag, 'segment_definition').'
            ORDER BY 1
        ', [
            $organizerId, $tagId,
            $organizerId, ...$statuses, $tagId,
        ])));
    }

    public function removeFromSignupForms(int $organizerId, int $tagId): void
    {
        $this->runQuery(fn () => $this->db->update("
            UPDATE crm_signup_forms
            SET tag_ids = COALESCE((SELECT jsonb_agg(element.value) FROM jsonb_array_elements(tag_ids) AS element(value) WHERE element.value <> to_jsonb(CAST(? AS bigint))), '[]'::jsonb),
                updated_at = now()
            WHERE organizer_id = ? AND tag_ids @> to_jsonb(ARRAY[CAST(? AS bigint)])
        ", [$tagId, $organizerId, $tagId]));
    }
}
