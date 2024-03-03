<?php

namespace HiEvents\Http\Actions\PromoCodes;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\PromoCode\CreateUpdatePromoCodeRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\PromoCode\PromoCodeResource;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\PromoCode\CreatePromoCodeHandler;
use HiEvents\Services\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreatePromoCodeAction extends BaseAction
{
    private CreatePromoCodeHandler $createPromoCodeHandler;

    public function __construct(CreatePromoCodeHandler $promoCodeHandler)
    {
        $this->createPromoCodeHandler = $promoCodeHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateUpdatePromoCodeRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $promoCode = $this->createPromoCodeHandler->handle($eventId, new UpsertPromoCodeDTO(
                code: strtolower($request->input('code')),
                event_id: $eventId,
                applicable_ticket_ids: $request->input('applicable_ticket_ids'),
                discount_type: PromoCodeDiscountTypeEnum::fromName($request->input('discount_type')),
                discount: $request->float('discount'),
                expiry_date: $request->input('expiry_date'),
                max_allowed_usages: $request->input('max_allowed_usages'),
            ));
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'code' => $e->getMessage(),
            ]);
        } catch (UnrecognizedTicketIdException $e) {
            throw ValidationException::withMessages([
                'applicable_ticket_ids' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            PromoCodeResource::class,
            $promoCode,
            ResponseCodes::HTTP_CREATED
        );
    }
}
