<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\User\SendUserInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class ResendInvitationAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    private SendUserInvitationService $invitationService;

    public function __construct(UserRepositoryInterface $userRepository, SendUserInvitationService $invitationService)
    {
        $this->userRepository = $userRepository;
        $this->invitationService = $invitationService;
    }

    public function __invoke(int $userId): JsonResponse|Response
    {
        $user = $this->userRepository->findByIdAndAccountId($userId, $this->getAuthenticatedAccountId());

        if ($user->getCurrentAccountUser()?->getStatus() !== UserStatus::INVITED->name) {
            return $this->errorResponse(__('User status is not Invited'));
        }

        try {
            $this->invitationService->sendInvitation($user, $this->getAuthenticatedAccountId());
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_FORBIDDEN);
        }

        return $this->noContentResponse();
    }
}
