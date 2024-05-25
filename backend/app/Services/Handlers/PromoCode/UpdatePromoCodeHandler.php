<?php

namespace HiEvents\Services\Handlers\PromoCode;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Ticket\EventTicketValidationService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;

readonly class UpdatePromoCodeHandler
{
    public function __construct(
        private PromoCodeRepositoryInterface $promoCodeRepository,
        private EventTicketValidationService $eventTicketValidationService,
        private EventRepositoryInterface     $eventRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws UnrecognizedTicketIdException
     */
    public function handle(int $promoCodeId, UpsertPromoCodeDTO $promoCodeDTO): PromoCodeDomainObject
    {
        $this->eventTicketValidationService->validateTicketIds(
            ticketIds: $promoCodeDTO->applicable_ticket_ids,
            eventId: $promoCodeDTO->event_id
        );

        $existing = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::EVENT_ID => $promoCodeDTO->event_id,
            PromoCodeDomainObjectAbstract::CODE => $promoCodeDTO->code,
        ]);

        if ($existing !== null && $existing->getId() !== $promoCodeId) {
            throw new ResourceConflictException(
                __('The code :code is already in use for this event', ['code' => $promoCodeDTO->code])
            );
        }

        $event = $this->eventRepository->findById($promoCodeDTO->event_id);

        return $this->promoCodeRepository->updateFromArray($promoCodeId, [
            PromoCodeDomainObjectAbstract::CODE => $promoCodeDTO->code,
            PromoCodeDomainObjectAbstract::DISCOUNT => $promoCodeDTO->discount_type === PromoCodeDiscountTypeEnum::NONE
                ? 0.00
                : (float)$promoCodeDTO->discount,
            PromoCodeDomainObjectAbstract::DISCOUNT_TYPE => $promoCodeDTO->discount_type?->name,
            PromoCodeDomainObjectAbstract::EXPIRY_DATE => $promoCodeDTO->expiry_date
                ? DateHelper::convertToUTC($promoCodeDTO->expiry_date, $event->getTimezone())
                : null,
            PromoCodeDomainObjectAbstract::MAX_ALLOWED_USAGES => $promoCodeDTO->max_allowed_usages,
            PromoCodeDomainObjectAbstract::APPLICABLE_TICKET_IDS => $promoCodeDTO->applicable_ticket_ids,
        ]);
    }
}
