<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentReachDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Exceptions\ResourceNotFoundException;

class CountSegmentHandler
{
    public function __construct(
        private readonly SegmentResolver $segmentResolver,
        private readonly SegmentCountService $countService,
        private readonly SmsConnectionService $smsConnectionService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function handle(int $organizerId, SegmentReferenceDTO $reference, bool $canViewFinancials): SegmentReachDTO
    {
        $definition = $this->segmentResolver->resolveDefinition($organizerId, $reference, $canViewFinancials);
        $count = $this->countService->count($organizerId, $definition);

        return new SegmentReachDTO(
            contacts_count: $count->contacts_count,
            mailable_count: $count->mailable_count,
            textable_count: $count->textable_count,
            textable_in_enabled_countries_count: $this->countService->countTextableIn(
                $organizerId,
                $definition,
                $this->smsConnectionService->enabledCountries($this->smsConnectionService->findForOrganizer($organizerId)),
            ),
        );
    }
}
