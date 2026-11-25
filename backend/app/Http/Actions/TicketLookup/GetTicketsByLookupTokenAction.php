<?php

namespace HiEvents\Http\Actions\TicketLookup;

use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\TicketLookup\LookupTicketResourcePublic;
use HiEvents\Services\Application\Handlers\TicketLookup\GetTicketsByLookupTokenHandler;
use Illuminate\Http\JsonResponse;

class GetTicketsByLookupTokenAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketsByLookupTokenHandler $handler,
    ) {}

    public function __invoke(string $token): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: LookupTicketResourcePublic::class,
                data: $this->handler->handle($token),
            );
        } catch (InvalidTicketLookupTokenException $exception) {
            return $this->errorResponse(message: $exception->getMessage());
        }
    }
}
