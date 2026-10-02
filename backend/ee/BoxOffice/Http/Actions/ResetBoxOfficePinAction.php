<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeWithPinResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\ResetBoxOfficePinHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;

class ResetBoxOfficePinAction extends BaseBoxOfficeAction
{
    public function __construct(
        private readonly ResetBoxOfficePinHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $boxOfficeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->assertBoxOfficeEnabled();

        return $this->resourceResponse(
            resource: BoxOfficeWithPinResource::class,
            data: $this->handler->handle($eventId, $boxOfficeId),
        );
    }
}
