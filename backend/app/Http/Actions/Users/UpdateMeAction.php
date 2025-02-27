<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\UpdateMeRequest;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Application\Handlers\User\UpdateMeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
                'account_id' => $this->getAuthenticatedAccountId(),
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'current_password' => $request->validated('current_password'),
                'timezone' => $request->validated('timezone'),
                'locale' => $request->validated('locale'),
            ]));

            return $this->resourceResponse(UserResource::class, $user);
        } catch (PasswordInvalidException) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password does not match our records.',
            ]);
        }
    }
}
