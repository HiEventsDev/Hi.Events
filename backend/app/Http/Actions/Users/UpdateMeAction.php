<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\User\UpdateMeRequest;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Application\Handlers\User\UpdateMeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
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
                'marketing_opt_in' => $request->has('marketing_opt_in') ? (bool) $request->validated('marketing_opt_in') : null,
                'notify_new_orders' => $request->has('notify_new_orders') ? (bool) $request->validated('notify_new_orders') : null,
                'confirmation_password' => $request->validated('confirmation_password'),
                'reauth_proof' => $request->validated('reauth_proof'),
                'token_id' => $this->getAuthTokenId(),
            ]));

            return $this->resourceResponse(UserResource::class, $user);
        } catch (PasswordInvalidException) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password does not match our records.',
            ]);
        } catch (IdentityConfirmationFailedException $exception) {
            throw ValidationException::withMessages([
                'confirmation_password' => $exception->getMessage(),
            ]);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }
    }
}
