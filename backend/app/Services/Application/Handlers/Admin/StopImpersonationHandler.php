<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Admin\DTO\StopImpersonationDTO;
use Illuminate\Auth\AuthManager;

class StopImpersonationHandler
{
    public function __construct(
        private readonly AuthManager $authManager,
    )
    {
    }

    public function handle(StopImpersonationDTO $dto): string
    {
        $impersonator = User::findOrFail($dto->impersonatorId);
        $impersonatorAccountId = $impersonator->accounts()->first()->id;

        return $this->authManager->claims([
            'account_id' => $impersonatorAccountId,
            'is_impersonating' => false,
            'impersonator_id' => null,
        ])->login($impersonator);
    }
}
