<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\CancelEmailChangeHandler;
use HiEvents\Services\Application\Handlers\User\DTO\CancelEmailChangeDTO;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
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

        if ($userId !== $this->getAuthenticatedUser()->getId()) {
            throw new UnauthorizedException;
        }

        $user = $this->cancelEmailChangeHandler->handle(
            new CancelEmailChangeDTO(
                userId: $userId,
                accountId: $this->getAuthenticatedAccountId(),
            )
        );

        return $this->resourceResponse(UserResource::class, $user);
    }
}
