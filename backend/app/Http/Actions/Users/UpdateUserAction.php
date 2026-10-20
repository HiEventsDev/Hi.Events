<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\CannotUpdateResourceException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\User\UpdateUserRequest;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateUserDTO;
use HiEvents\Services\Application\Handlers\User\UpdateUserHandler;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;
use HiEvents\Services\Domain\Auth\Access\Exception\InvalidAccessGrantException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class UpdateUserAction extends BaseAction
{
    private UpdateUserHandler $updateUserHandler;

    public function __construct(UpdateUserHandler $updateUserHandler)
    {
        $this->updateUserHandler = $updateUserHandler;
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(UpdateUserRequest $request, int $userId): JsonResponse
    {
        $this->isActionAuthorized(
            entityId: $userId,
            entityType: UserDomainObject::class,
        );

        $authenticatedUser = $this->getAuthenticatedUser();

        $userData = $request->validated() + [
            'id' => $userId,
            'account_id' => $this->getAuthenticatedAccountId(),
            'updated_by_user_id' => $authenticatedUser->getId(),
            'access' => $request->has('access_scope') ? AccountUserAccessData::buildFromValidated($request->validated()) : null,
        ];

        try {
            $user = $this->updateUserHandler->handle(UpdateUserDTO::fromArray($userData));
        } catch (CannotUpdateResourceException $e) {
            throw ValidationException::withMessages([
                'role' => $e->getMessage(),
            ]);
        } catch (InvalidAccessGrantException $e) {
            throw ValidationException::withMessages([
                'access_scope' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(UserResource::class, $user);
    }
}
