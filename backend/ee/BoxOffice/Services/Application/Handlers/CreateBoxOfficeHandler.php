<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\UpsertBoxOfficeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\CreateBoxOfficeService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeWithPinDTO;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

class CreateBoxOfficeHandler
{
    public function __construct(
        private readonly CreateBoxOfficeService $createBoxOfficeService,
        private readonly BoxOfficePinService $pinService,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     */
    public function handle(UpsertBoxOfficeDTO $data): BoxOfficeWithPinDTO
    {
        $boxOffice = (new BoxOfficeDomainObject)
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

        $pin = $this->pinService->generate();

        $created = $this->createBoxOfficeService->createBoxOffice(
            boxOffice: $boxOffice,
            productIds: $data->product_ids,
            pin: $pin,
        );

        $this->featureUsage->forget();

        return new BoxOfficeWithPinDTO(
            box_office: $created,
            pin: $pin,
        );
    }
}
