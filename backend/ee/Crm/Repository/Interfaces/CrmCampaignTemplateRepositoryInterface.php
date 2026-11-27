<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmCampaignTemplateDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmCampaignTemplateDomainObject>
 */
interface CrmCampaignTemplateRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, CrmCampaignTemplateDomainObject>
     */
    public function findForOrganizer(int $organizerId): Collection;

    public function nameIsTaken(int $organizerId, string $name, ?int $exceptTemplateId): bool;

    public function markUsed(int $organizerId, int $templateId): void;
}
