<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\LookupKioskTicketsRequest;
use HiEvents\Resources\CheckInList\KioskTicketPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\LookupKioskTicketsPublicDTO;
use HiEvents\Services\Application\Handlers\CheckInList\Public\LookupKioskTicketsPublicHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LookupKioskTicketsPublicAction extends BaseAction
{
    public function __construct(
        private readonly LookupKioskTicketsPublicHandler $handler,
    ) {}

    public function __invoke(string $checkInListShortId, LookupKioskTicketsRequest $request): JsonResponse
    {
        try {
            $tickets = $this->handler->handle(new LookupKioskTicketsPublicDTO(
                checkInListShortId: $checkInListShortId,
                email: $request->validated('email'),
                lastName: $request->validated('last_name'),
            ));
        } catch (CannotCheckInException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: KioskTicketPublicResource::class,
            data: $tickets,
        );
    }
}
