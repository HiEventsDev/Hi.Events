<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\UpsertBoxOfficeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\UpdateBoxOfficeService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

class UpdateBoxOfficeHandler
{
    public function __construct(
        private readonly UpdateBoxOfficeService $updateBoxOfficeService,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertBoxOfficeDTO $data): BoxOfficeDomainObject
    {
        $boxOffice = (new BoxOfficeDomainObject)
            ->setId($data->id)
            ->setName($data->name)
            ->setDescription($data->description)
            ->setEventId($data->event_id)
            ->setEventOccurrenceId($data->event_occurrence_id)
            ->setCheckInListId($data->check_in_list_id)
            ->setAllowPriceOverride($data->allow_price_override)
            ->setAllowDiscounts($data->allow_discounts)
            ->setCollectOrderQuestions($data->collect_order_questions)
            ->setActivatesAt($data->activates_at)
            ->setExpiresAt($data->expires_at);

        return $this->updateBoxOfficeService->updateBoxOffice(
            boxOffice: $boxOffice,
            productIds: $data->product_ids,
        );
    }
}
