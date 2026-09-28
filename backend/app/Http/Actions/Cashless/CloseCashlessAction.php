<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CashlessClosureNotAllowedException;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessClosureResultResource;
use HiEvents\Services\Application\Handlers\Cashless\CloseCashlessHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class CloseCashlessAction extends BaseAction
{
    public function __construct(
        private readonly CloseCashlessHandler $closeCashlessHandler,
    ) {}

    /**
     * Close cashless for an event
     *
     * Moves every remaining balance into the event's total sales and locks all wallets. This cannot be undone.
     *
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $result = $this->closeCashlessHandler->handle(
                eventId: $eventId,
                userId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (CashlessClosureNotAllowedException|CashlessNotEnabledException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: CashlessClosureResultResource::class,
            data: $result,
        );
    }
}
