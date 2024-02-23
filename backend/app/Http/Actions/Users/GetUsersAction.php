<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Resources\User\UserResource;

class GetUsersAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        return $this->resourceResponse(
            UserResource::class,
            $this->userRepository->findUsersByAccountId($this->getAuthenticatedUser()->getAccountId()),
        );
    }
}
