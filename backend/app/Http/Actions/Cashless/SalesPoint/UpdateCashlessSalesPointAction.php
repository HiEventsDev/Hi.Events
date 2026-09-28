<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\SalesPoint;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\UpsertCashlessSalesPointRequest;
use HiEvents\Resources\Cashless\CashlessSalesPointResource;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpsertCashlessSalesPointDTO;
use HiEvents\Services\Application\Handlers\Cashless\SalesPoint\UpdateCashlessSalesPointHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateCashlessSalesPointAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCashlessSalesPointHandler $updateCashlessSalesPointHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(
        UpsertCashlessSalesPointRequest $request,
        int $eventId,
        int $salesPointId,
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $salesPoint = $this->updateCashlessSalesPointHandler->handle(UpsertCashlessSalesPointDTO::from([
                ...$request->validated(),
                'event_id' => $eventId,
                'id' => $salesPointId,
            ]));
        } catch (UnrecognizedProductIdException $e) {
            throw ValidationException::withMessages([
                'product_ids' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: CashlessSalesPointResource::class,
            data: $salesPoint,
        );
    }
}
