<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\StopImpersonationDTO;
use Illuminate\Auth\AuthManager;

class StopImpersonationHandler
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function handle(StopImpersonationDTO $dto): string
    {
        $superAdminMembership = $this->accountUserRepository->findFirstWhere([
            'user_id' => $dto->impersonatorId,
            'role' => Role::SUPERADMIN->name,
        ]);

        if ($superAdminMembership === null) {
            throw new UnauthorizedException(__('Impersonation not allowed'));
        }

        return $this->authManager->claims([
            'account_id' => $superAdminMembership->getAccountId(),
            'role' => Role::SUPERADMIN->value,
            'auth_method' => AuthMethod::PASSWORD->value,
            'is_impersonating' => false,
            'impersonator_id' => null,
        ])->login(User::findOrFail($dto->impersonatorId));
    }
}
