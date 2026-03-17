<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Organizer\DeleteOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\DeleteOrganizerDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeleteOrganizerAction extends BaseAction
{
    public function __construct(
        private readonly DeleteOrganizerHandler $deleteOrganizerHandler,
    )
    {
    }

    public function __invoke(int $organizerId): Response|JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);

        try {
            $this->deleteOrganizerHandler->handle(DeleteOrganizerDTO::fromArray([
                'organizerId' => $organizerId,
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
