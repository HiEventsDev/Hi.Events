<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\Status\UserStatus;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

class DeleteInvitationAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(int $userId): JsonResponse|Response
    {
        $this->isActionAuthorized($userId, UserDomainObject::class, Role::ADMIN);

        $user = $this->userRepository->findById($userId);

        if ($user->getStatus() !== UserStatus::INVITED->name) {
            return $this->errorResponse(__('Not invitation found for this user.'));
        }

        $this->userRepository->deleteWhere([
            'id' => $userId,
            'status' => UserStatus::INVITED->name,
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
        ]);

        return $this->noContentResponse();
    }
}
