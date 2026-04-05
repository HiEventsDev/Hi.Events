<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\CreateManualOrderRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Order\CreateManualOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateManualOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Application\Locale\LocaleService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateManualOrderAction extends BaseAction
{
    public function __construct(
        private readonly CreateManualOrderHandler $handler,
        private readonly LocaleService            $localeService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(CreateManualOrderRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $order = $this->handler->handle(CreateManualOrderDTO::fromArray([
            'event_id' => $eventId,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'send_confirmation_email' => $request->boolean('send_confirmation_email', true),
            'products' => ProductOrderDetailsDTO::collectionFromArray($request->input('products')),
            'promo_code' => $request->input('promo_code'),
            'notes' => $request->input('notes'),
            'locale' => $this->localeService->getLocaleOrDefault($request->input('locale')),
        ]));

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $order,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
