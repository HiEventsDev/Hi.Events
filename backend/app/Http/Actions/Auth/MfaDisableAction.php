<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaDisableAction extends BaseAction
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

        if (!$userModel->mfa_enabled) {
            return $this->errorResponse(
                message: __('MFA is not enabled'),
                statusCode: 409,
            );
        }

        // Verify password before allowing MFA disable
        if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $userModel->password)) {
            return $this->errorResponse(
                message: __('Invalid password'),
                statusCode: 403,
            );
        }

        $this->mfaService->disableMfa($userModel, $request);

        return $this->jsonResponse([
            'message' => __('MFA has been disabled'),
        ]);
    }
}
