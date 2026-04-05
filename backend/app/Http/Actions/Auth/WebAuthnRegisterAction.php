<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnRegisterAction extends BaseAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.response.attestationObject' => ['required', 'string'],
            'credential.response.clientDataJSON' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->getAuthenticatedUser();
        $userModel = \HiEvents\Models\User::findOrFail($user->getId());

        try {
            $credential = $this->webAuthnService->verifyRegistration(
                user: $userModel,
                credential: $request->input('credential'),
                name: $request->input('name'),
                request: $request,
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }

        return $this->jsonResponse([
            'message' => __('Passkey registered successfully'),
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'created_at' => $credential->created_at,
            ],
        ], 201);
    }
}
