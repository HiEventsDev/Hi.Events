<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmSegmentDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentUsageDTO;
use HiEvents\Models\CrmSegment;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmSegmentDomainObject>
 */
class CrmSegmentRepository extends BaseRepository implements CrmSegmentRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmSegment::class;
    }

    public function getDomainObject(): string
    {
        return CrmSegmentDomainObject::class;
    }

    public function findUsages(int $organizerId, array $segmentIds): Collection
    {
        if ($segmentIds === []) {
            return collect();
        }

        $campaigns = $this->db->table('crm_campaigns')
            ->where('organizer_id', $organizerId)
            ->whereIn('segment_id', $segmentIds)
            ->whereIn('status', CrmCampaignStatus::valuesOf(CrmCampaignStatus::resolvingAudienceLater()))
            ->selectRaw('segment_id, ? AS type, id, name, NULL AS trigger, status', [SegmentUsageDTO::TYPE_CAMPAIGN]);

        $automations = $this->db->table('crm_automations')
            ->where('organizer_id', $organizerId)
            ->whereIn('segment_id', $segmentIds)
            ->whereNull('deleted_at')
            ->selectRaw(
                'segment_id, ? AS type, id, name, trigger, CASE WHEN is_enabled THEN ? ELSE ? END AS status',
                [SegmentUsageDTO::TYPE_AUTOMATION, SegmentUsageDTO::STATUS_ENABLED, SegmentUsageDTO::STATUS_DISABLED],
            );

        return $this->runQuery(fn () => $campaigns->unionAll($automations)
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row) => new SegmentUsageDTO(
                segment_id: (int) $row->segment_id,
                type: $row->type,
                id: (int) $row->id,
                name: $row->name,
                trigger: $row->trigger,
                status: $row->status,
                blocks_deletion: in_array($row->status, [CrmCampaignStatus::SCHEDULED->value, SegmentUsageDTO::STATUS_ENABLED], true),
            )));
    }
}
