<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use TicketKitten\Exceptions\PasswordInvalidException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpdateMeDTO;
use TicketKitten\Http\Request\User\UpdateMeRequest;
use TicketKitten\Resources\User\UserResource;
use TicketKitten\Service\Handler\User\UpdateMeHandler;

class UpdateMeAction extends BaseAction
{
    private UpdateMeHandler $updateUserHandler;

    public function __construct(UpdateMeHandler $updateUserHandler)
    {
        $this->updateUserHandler = $updateUserHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateMeRequest $request): JsonResponse
    {
        try {
            $user = $this->updateUserHandler->handle(UpdateMeDTO::fromArray([
                'id' => $this->getAuthenticatedUser()->getId(),
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'current_password' => $request->validated('current_password'),
                'timezone' => $request->validated('timezone'),
            ]));

            return $this->resourceResponse(UserResource::class, $user);
        } catch (PasswordInvalidException) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password does not match our records.',
            ]);
        }
    }
}
