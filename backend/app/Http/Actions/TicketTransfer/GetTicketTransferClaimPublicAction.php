<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\TicketTransfer\TicketTransferClaimResourcePublic;
use HiEvents\Services\Application\Handlers\TicketTransfer\GetTicketTransferClaimHandler;
use Illuminate\Http\JsonResponse;

class GetTicketTransferClaimPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketTransferClaimHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(string $token): JsonResponse
    {
        return $this->resourceResponse(
            resource: TicketTransferClaimResourcePublic::class,
            data: $this->handler->handle($token),
        );
    }
}
