<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;

class MfaStatusAction extends BaseAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
    )
    {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        return $this->jsonResponse([
            'mfa_enabled' => (bool)$userModel->mfa_enabled,
            'mfa_confirmed_at' => $userModel->mfa_confirmed_at,
            'passkey_enabled' => (bool)$userModel->passkey_enabled,
            'passkeys' => $this->webAuthnService->listCredentials($userModel),
            'oauth_provider' => $userModel->oauth_provider,
        ]);
    }
}
