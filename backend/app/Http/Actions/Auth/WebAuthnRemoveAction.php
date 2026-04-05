<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnRemoveAction extends BaseAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
    )
    {
    }

    public function __invoke(Request $request, int $credentialId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        try {
            $this->webAuthnService->removeCredential($userModel, $credentialId, $request);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->errorResponse(
                message: __('Passkey not found'),
                statusCode: 404,
            );
        }

        return $this->jsonResponse([
            'message' => __('Passkey removed successfully'),
        ]);
    }
}
