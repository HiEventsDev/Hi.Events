<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnLoginOptionsAction extends BaseAuthAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $options = $this->webAuthnService->generateAuthenticationOptions(
            email: $request->input('email'),
        );

        return $this->jsonResponse($options);
    }
}
