<?php

namespace HiEvents\Http\Actions\SelfService\Verification;

use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\SelfService\SelfServiceSessionResource;
use HiEvents\Services\Application\Handlers\SelfService\Verification\StartSessionFromTicketLookupHandler;
use Illuminate\Http\JsonResponse;

class StartSessionFromTicketLookupPublicAction extends BaseAction
{
    public function __construct(
        private readonly StartSessionFromTicketLookupHandler $handler,
    ) {}

    public function __invoke(string $token): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: SelfServiceSessionResource::class,
                data: $this->handler->handle($token),
            );
        } catch (InvalidTicketLookupTokenException $exception) {
            return $this->errorResponse(message: $exception->getMessage());
        }
    }
}
