<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Location\DeleteLocationHandler;
use Illuminate\Http\JsonResponse;

class DeleteLocationAction extends BaseAction
{
    public function __construct(
        private readonly DeleteLocationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $locationId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        try {
            $this->handler->handle($organizerId, $this->getAuthenticatedAccountId(), $locationId);
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_CONFLICT,
            );
        }

        return $this->deletedResponse();
    }
}
