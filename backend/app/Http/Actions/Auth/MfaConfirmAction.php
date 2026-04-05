<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaConfirmAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'secret' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        $result = $this->mfaService->enableMfa(
            user: $userModel,
            secret: $request->input('secret'),
            code: $request->input('code'),
            request: $request,
        );

        if (!$result['success']) {
            return $this->errorResponse(
                message: $result['error'],
                statusCode: 422,
            );
        }

        return $this->jsonResponse([
            'message' => __('MFA has been enabled'),
            'recovery_codes' => $result['recovery_codes'],
        ]);
    }
}
