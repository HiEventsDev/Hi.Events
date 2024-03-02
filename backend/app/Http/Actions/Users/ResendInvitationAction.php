<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Common\User\SendUserInvitationService;
use Illuminate\Http\Response;

class ResendInvitationAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    private SendUserInvitationService $invitationService;

    public function __construct(UserRepositoryInterface $userRepository, SendUserInvitationService $invitationService)
    {
        $this->userRepository = $userRepository;
        $this->invitationService = $invitationService;
    }

    public function __invoke(int $userId): Response
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $user = $this->userRepository->findFirstWhere([
            'id' => $userId,
            'status' => UserStatus::INVITED->name,
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
        ]);

        if (!$user) {
            return $this->notFoundResponse();
        }

        $this->invitationService->sendInvitation($user);

        return $this->noContentResponse();
    }
}
