<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Resources\User\UserResource;

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
