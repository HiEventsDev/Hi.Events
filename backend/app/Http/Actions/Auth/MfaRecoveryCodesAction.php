<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaRecoveryCodesAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $userModel->password)) {
            return $this->errorResponse(
                message: __('Invalid password'),
                statusCode: 403,
            );
        }

        $codes = $this->mfaService->regenerateRecoveryCodes($userModel, $request);

        return $this->jsonResponse([
            'recovery_codes' => $codes,
        ]);
    }
}
