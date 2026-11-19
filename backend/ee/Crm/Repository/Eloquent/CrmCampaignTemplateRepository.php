<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmCampaignTemplateDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignTemplateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignTemplateRepositoryInterface;
use HiEvents\Models\CrmCampaignTemplate;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmCampaignTemplateDomainObject>
 */
class CrmCampaignTemplateRepository extends BaseRepository implements CrmCampaignTemplateRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmCampaignTemplate::class;
    }

    public function getDomainObject(): string
    {
        return CrmCampaignTemplateDomainObject::class;
    }

    public function findForOrganizer(int $organizerId): Collection
    {
        return $this->runQuery(fn () => $this->handleResults($this->model
            ->where(CrmCampaignTemplateDomainObjectAbstract::ORGANIZER_ID, $organizerId)
            ->orderByRaw('last_used_at DESC NULLS LAST')
            ->orderByDesc(CrmCampaignTemplateDomainObjectAbstract::UPDATED_AT)
            ->get()));
    }

    public function nameIsTaken(int $organizerId, string $name, ?int $exceptTemplateId): bool
    {
        return $this->runQuery(fn () => $this->db->table('crm_campaign_templates')
            ->where('organizer_id', $organizerId)
            ->whereNull('deleted_at')
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptTemplateId !== null, static fn ($query) => $query->where('id', '!=', $exceptTemplateId))
            ->exists());
    }

    public function markUsed(int $organizerId, int $templateId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_campaign_templates')
            ->where('organizer_id', $organizerId)
            ->where('id', $templateId)
            ->whereNull('deleted_at')
            ->update(['last_used_at' => now()]));
    }
}
