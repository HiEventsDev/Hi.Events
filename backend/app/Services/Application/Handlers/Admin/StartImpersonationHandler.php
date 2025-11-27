<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\StartImpersonationDTO;
use Illuminate\Auth\AuthManager;

class StartImpersonationHandler
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AuthManager                    $authManager,
    )
    {
    }

    /**
     * @throws UnauthorizedException
     */
    public function handle(StartImpersonationDTO $dto): string
    {
        /** @var User $targetUser */
        $targetUser = User::findOrFail($dto->userId);

        $accountUser = $this->accountUserRepository->findFirstWhere([
            'user_id' => $targetUser->id,
            'account_id' => $dto->accountId
        ]);

        if (!$accountUser) {
            throw new UnauthorizedException(__('User does not belong to this account'));
        }

        if (!$this->authManager->user()?->canImpersonate() || $accountUser->getRole() === Role::SUPERADMIN->name) {
            throw new UnauthorizedException(__('Impersonation not allowed'));
        }

        return $this->authManager->claims([
            'account_id' => $dto->accountId,
            'impersonator_id' => $dto->impersonatorId,
            'is_impersonating' => true,
        ])->login($targetUser);
    }
}
