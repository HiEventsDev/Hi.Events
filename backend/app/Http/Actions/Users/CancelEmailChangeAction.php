<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Resources\User\UserResource;
use TicketKitten\Service\Handler\User\CancelEmailChangeHandler;

class CancelEmailChangeAction extends BaseAction
{
    private CancelEmailChangeHandler $cancelEmailChangeHandler;

    public function __construct(CancelEmailChangeHandler $cancelEmailChangeHandler)
    {
        $this->cancelEmailChangeHandler = $cancelEmailChangeHandler;
    }

    public function __invoke(int $userId): JsonResponse
    {
        $this->isActionAuthorized($userId, UserDomainObject::class);

        $user = $this->cancelEmailChangeHandler->handle($this->getAuthenticatedUser()->getId());

        return $this->resourceResponse(UserResource::class, $user);
    }
}
