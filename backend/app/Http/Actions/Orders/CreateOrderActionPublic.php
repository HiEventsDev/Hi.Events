<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\CreateOrderRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Locale\LocaleService;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;
use HiEvents\Services\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Handlers\Order\DTO\TicketOrderDetailsDTO;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateOrderHandler                  $orderHandler,
        private readonly OrderCreateRequestValidationService $orderCreateRequestValidationService,
        private readonly CheckoutSessionManagementService    $sessionIdentifierService,
        private readonly LocaleService                        $localeService,

    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(CreateOrderRequest $request, int $eventId): JsonResponse
    {
        $this->orderCreateRequestValidationService->validateRequestData($eventId, $request->all());

        $order = $this->orderHandler->handle(
            $eventId,
            CreateOrderPublicDTO::fromArray([
                'is_user_authenticated' => $this->isUserAuthenticated(),
                'promo_code' => $request->input('promo_code'),
                'tickets' => TicketOrderDetailsDTO::collectionFromArray($request->input('tickets')),
                'session_identifier' => $this->sessionIdentifierService->getSessionId(),
                'order_locale' => $this->localeService->getLocaleOrDefault($request->getPreferredLanguage()),
            ])
        );

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
