<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\CreateUserDTO;
use HiEvents\Http\Request\User\CreateUserRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\User\UserResource;
use HiEvents\Service\Handler\User\CreateUserHandler;

class CreateUserAction extends BaseAction
{
    private CreateUserHandler $createUserHandler;

    public function __construct(CreateUserHandler $createUserHandler)
    {
        $this->createUserHandler = $createUserHandler;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateUserRequest $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $data = array_merge($request->validated(), [
            'invited_by' => $this->getAuthenticatedUser()->getId(),
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
        ]);

        try {
            $user = $this->createUserHandler->handle(CreateUserDTO::fromArray($data));
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
