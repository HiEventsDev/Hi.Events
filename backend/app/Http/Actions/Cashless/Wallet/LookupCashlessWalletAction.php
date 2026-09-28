<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\LookupCashlessWalletRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessWalletResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\LookupCashlessWalletHandler;
use Illuminate\Http\JsonResponse;

class LookupCashlessWalletAction extends BaseAction
{
    public function __construct(
        private readonly LookupCashlessWalletHandler $lookupCashlessWalletHandler,
    ) {}

    /**
     * Find a ticket's cashless balance by its public id
     *
     * Creates the balance if the ticket has never held one, so staff can load a ticket the first
     * time they scan it.
     */
    public function __invoke(LookupCashlessWalletRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $wallet = $this->lookupCashlessWalletHandler->handle(
                eventId: $eventId,
                attendeePublicId: $request->input('attendee_public_id'),
            );
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessWalletResource::class,
            data: $wallet,
        );
    }
}
