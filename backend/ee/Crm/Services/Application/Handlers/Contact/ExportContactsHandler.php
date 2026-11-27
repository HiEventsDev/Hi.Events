<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use Generator;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\GetContactsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;

class ExportContactsHandler
{
    private const PAGE_SIZE = 1000;

    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly ContactEnrichmentService $enrichmentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function handle(GetContactsDTO $dto): Generator
    {
        return $this->pages(
            $dto,
            $dto->contact_ids === null ? $this->segmentResolver->resolve($dto->organizer_id, $dto->segment, $dto->can_view_financials) : null,
        );
    }

    private function pages(GetContactsDTO $dto, ?CompiledSegmentDTO $segment): Generator
    {
        $afterEmail = null;

        do {
            $contacts = $this->contactRepository->findForExport(
                organizerId: $dto->organizer_id,
                query: $dto->contact_ids === null ? $dto->query_params->query : null,
                segment: $segment,
                afterEmail: $afterEmail,
                limit: self::PAGE_SIZE,
                contactIds: $dto->contact_ids,
            );

            if ($contacts->isEmpty()) {
                return;
            }

            $this->enrichmentService->attachTagsAndSpend($contacts);

            yield $contacts;

            $afterEmail = $contacts->last()->getEmail();
        } while ($contacts->count() === self::PAGE_SIZE);
    }
}
