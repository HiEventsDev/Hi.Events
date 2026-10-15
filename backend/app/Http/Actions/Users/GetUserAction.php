<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class GetUserAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(int $userId): JsonResponse
    {
        $user = $this->userRepository->findByIdAndAccountId($userId, $this->getAuthenticatedAccountId());

        if (! $user) {
            throw new ResourceNotFoundException;
        }

        return $this->resourceResponse(
            resource: UserResource::class,
            data: $user
        );
    }
}
