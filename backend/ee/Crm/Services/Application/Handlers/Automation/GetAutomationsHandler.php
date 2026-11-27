<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use Illuminate\Support\Collection;

class GetAutomationsHandler
{
    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
    ) {}

    /**
     * @return Collection<int, CrmAutomationDomainObject>
     */
    public function handle(int $organizerId): Collection
    {
        return $this->automationRepository->findByOrganizerIdWithStats($organizerId);
    }
}
