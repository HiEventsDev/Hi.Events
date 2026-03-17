<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Event\DeleteEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\DeleteEventDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeleteEventAction extends BaseAction
{
    public function __construct(
        private readonly DeleteEventHandler $deleteEventHandler,
    )
    {
    }

    public function __invoke(int $eventId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class, Role::ADMIN);

        try {
            $this->deleteEventHandler->handle(DeleteEventDTO::fromArray([
                'eventId' => $eventId,
                'accountId' => $this->getAuthenticatedAccountId(),
            ]));
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->deletedResponse();
    }
}
