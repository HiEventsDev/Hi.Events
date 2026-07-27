<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Organizer\Vat;

use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;

class GetOrganizerVatSettingHandler
{
    public function __construct(
        private readonly OrganizerVatSettingRepositoryInterface $vatSettingRepository,
    ) {}

    public function handle(int $organizerId): ?OrganizerVatSettingDomainObject
    {
        return $this->vatSettingRepository->findByOrganizerId($organizerId);
    }
}
