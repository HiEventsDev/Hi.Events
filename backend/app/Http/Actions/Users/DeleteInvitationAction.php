<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

        $user = $this->userRepository->findByIdAndAccountId($userId, $this->getAuthenticatedAccountId());

        if ($user->getCurrentAccountUser()?->getStatus() !== UserStatus::INVITED->name) {
            return $this->errorResponse(__('No invitation found for this user.'));
        }

        $this->userRepository->deleteWhere([
            'id' => $userId,
        ]);

        return $this->noContentResponse();
    }
}
