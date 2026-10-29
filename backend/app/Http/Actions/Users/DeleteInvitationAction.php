<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class DeleteInvitationAction extends BaseAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function __invoke(int $userId): JsonResponse|Response
    {
        $this->isActionAuthorized($userId, UserDomainObject::class);

        $accountId = $this->getAuthenticatedAccountId();
        $user = $this->userRepository->findByIdAndAccountId($userId, $accountId);

        if ($user->getCurrentAccountUser()?->getStatus() !== UserStatus::INVITED->name) {
            return $this->errorResponse(__('No invitation found for this user.'));
        }

        $this->databaseManager->transaction(function () use ($userId, $accountId) {
            $this->accountUserRepository->deleteWhere([
                'user_id' => $userId,
                'account_id' => $accountId,
            ]);

            if ($this->accountUserRepository->findWhere(['user_id' => $userId])->isEmpty()) {
                $this->userRepository->deleteWhere(['id' => $userId]);
            }
        });

        return $this->noContentResponse();
    }
}
