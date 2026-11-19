<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\GetContactsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class GetContactsHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly ContactEnrichmentService $enrichmentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function handle(GetContactsDTO $dto): LengthAwarePaginator
    {
        $contacts = $this->contactRepository->findByOrganizerId(
            organizerId: $dto->organizer_id,
            params: $dto->query_params,
            segment: $this->segmentResolver->resolve($dto->organizer_id, $dto->segment, $dto->can_view_financials),
        );

        $this->enrichmentService->attachTagsAndSpend(collect($contacts->items()));

        if ($dto->with_suppression) {
            $this->enrichmentService->attachSuppressions($dto->organizer_id, collect($contacts->items()));
        }

        return $contacts;
    }
}
