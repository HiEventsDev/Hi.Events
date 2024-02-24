<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Resources\User\UserResource;

class GetUserAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(int $userId): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $user = $this->userRepository->findFirstWhere([
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            'id' => $userId,
        ]);

        if (!$user) {
            throw new ResourceNotFoundException();
        }

        return $this->resourceResponse(
            UserResource::class,
            $user
        );
    }
}
