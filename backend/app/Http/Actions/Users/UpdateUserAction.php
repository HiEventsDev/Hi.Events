<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Exceptions\CannotUpdateResourceException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\UpdateUserRequest;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Handlers\User\DTO\UpdateUserDTO;
use HiEvents\Services\Handlers\User\UpdateUserHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateUserAction extends BaseAction
{
    private UpdateUserHandler $updateUserHandler;

    public function __construct(UpdateUserHandler $updateUserHandler)
    {
        $this->updateUserHandler = $updateUserHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateUserRequest $request, int $userId): JsonResponse
    {
        $authenticatedUser = $this->getAuthenticatedUser();

        $userData = $request->validated() + [
                'id' => $userId,
                'account_id' => $authenticatedUser->getAccountId(),
                'updated_by_user_id' => $authenticatedUser->getId(),
            ];

        try {
            $user = $this->updateUserHandler->handle(UpdateUserDTO::fromArray($userData));
        } catch (CannotUpdateResourceException $e) {
            throw ValidationException::withMessages([
                'role' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(UserResource::class, $user);
    }
}
