<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;

class WebAuthnRegisterOptionsAction extends BaseAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
    )
    {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::with('webAuthnCredentials')->findOrFail($user->getId());

        $options = $this->webAuthnService->generateRegistrationOptions($userModel);

        return $this->jsonResponse($options);
    }
}
