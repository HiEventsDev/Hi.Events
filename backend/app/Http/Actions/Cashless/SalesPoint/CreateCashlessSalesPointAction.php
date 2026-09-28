<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\SalesPoint;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\UpsertCashlessSalesPointRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessSalesPointResource;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpsertCashlessSalesPointDTO;
use HiEvents\Services\Application\Handlers\Cashless\SalesPoint\CreateCashlessSalesPointHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateCashlessSalesPointAction extends BaseAction
{
    public function __construct(
        private readonly CreateCashlessSalesPointHandler $createCashlessSalesPointHandler,
    ) {}

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(UpsertCashlessSalesPointRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $salesPoint = $this->createCashlessSalesPointHandler->handle(UpsertCashlessSalesPointDTO::from([
                ...$request->validated(),
                'event_id' => $eventId,
            ]));
        } catch (UnrecognizedProductIdException $e) {
            throw ValidationException::withMessages([
                'product_ids' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: CashlessSalesPointResource::class,
            data: $salesPoint,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
