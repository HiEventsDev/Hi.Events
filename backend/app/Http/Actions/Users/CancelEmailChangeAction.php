<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Handlers\User\CancelEmailChangeHandler;
use HiEvents\Services\Handlers\User\DTO\CancelEmailChangeDTO;
use Illuminate\Http\JsonResponse;

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

        $user = $this->cancelEmailChangeHandler->handle(
            new CancelEmailChangeDTO(
                userId: $userId,
                accountId: $this->getAuthenticatedAccountId(),
            )
        );

        return $this->resourceResponse(UserResource::class, $user);
    }
}
