<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\CreateUserRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\User\CreateUserHandler;
use HiEvents\Services\Application\Handlers\User\DTO\CreateUserDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateUserAction extends BaseAction
{
    public function __construct(
        private readonly CreateUserHandler $createUserHandler
    )
    {
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(CreateUserRequest $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $data = array_merge($request->validated(), [
            'invited_by' => $this->getAuthenticatedUser()->getId(),
            'account_id' => $this->getAuthenticatedAccountId(),
        ]);

        try {
            $user = $this->createUserHandler->handle(CreateUserDTO::from($data));
        } catch (ResourceConflictException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: UserResource::class,
            data: $user,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
