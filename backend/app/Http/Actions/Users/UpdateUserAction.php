<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use TicketKitten\Exceptions\CannotUpdateResourceException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpdateUserDTO;
use TicketKitten\Http\Request\User\UpdateUserRequest;
use TicketKitten\Resources\User\UserResource;
use TicketKitten\Service\Handler\User\UpdateUserHandler;

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
