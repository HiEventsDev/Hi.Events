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
use HiEvents\Services\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Handlers\PromoCode\UpdatePromoCodeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdatePromoCodeAction extends BaseAction
{
    private UpdatePromoCodeHandler $updatePromoCodeHandler;

    public function __construct(UpdatePromoCodeHandler $promoCodeHandler)
    {
        $this->updatePromoCodeHandler = $promoCodeHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateUpdatePromoCodeRequest $request, int $eventId, int $promoCodeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $promoCode = $this->updatePromoCodeHandler->handle($promoCodeId, new UpsertPromoCodeDTO(
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
            resource: PromoCodeResource::class,
            data: $promoCode,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
