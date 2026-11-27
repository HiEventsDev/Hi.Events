<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmImportRepositoryInterface;
use Illuminate\Support\Collection;

class GetContactImportsHandler
{
    private const RECENT_IMPORTS = 20;

    public function __construct(
        private readonly CrmImportRepositoryInterface $importRepository,
    ) {}

    /**
     * @return Collection<int, CrmImportDomainObject>
     */
    public function handle(int $organizerId): Collection
    {
        $this->importRepository->failStalled($organizerId);

        return $this->importRepository->findRecentForOrganizer($organizerId, self::RECENT_IMPORTS);
    }
}
