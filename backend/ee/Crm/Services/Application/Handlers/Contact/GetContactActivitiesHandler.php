<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactActivityRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetContactActivitiesHandler
{
    public function __construct(
        private readonly CrmContactActivityRepositoryInterface $activityRepository,
        private readonly ContactLookupService $contactLookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

        return $this->activityRepository->findForContact($contact->getId(), $params->page ?? 1, $params->per_page ?? 25);
    }
}
