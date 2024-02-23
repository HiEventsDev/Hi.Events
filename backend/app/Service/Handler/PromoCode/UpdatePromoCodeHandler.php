<?php

namespace TicketKitten\Service\Handler\PromoCode;

use TicketKitten\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use TicketKitten\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use TicketKitten\DomainObjects\PromoCodeDomainObject;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Helper\DateHelper;
use TicketKitten\Http\DataTransferObjects\UpsertPromoCodeDTO;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Service\Common\Ticket\EventTicketValidationService;
use TicketKitten\Service\Common\Ticket\Exception\UnrecognizedTicketIdException;

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
