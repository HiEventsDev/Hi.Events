<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\Status\UserStatus;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Service\Common\User\SendUserInvitationService;

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
