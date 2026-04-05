<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaSetupAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        if ($userModel->mfa_enabled) {
            return $this->errorResponse(
                message: __('MFA is already enabled for this account'),
                statusCode: 409,
            );
        }

        $secret = $this->mfaService->generateSecret();
        $qrCodeUrl = $this->mfaService->getQrCodeUrl($user->getEmail(), $secret);

        return $this->jsonResponse([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }
}
