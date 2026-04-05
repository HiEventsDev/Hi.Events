<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaVerifyAction extends BaseAuthAction
{
    public function __construct(
        private readonly MfaService   $mfaService,
        private readonly LoginService $loginService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'mfa_token' => ['required', 'string'],
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
            'account_id' => ['nullable', 'integer'],
        ]);

        // The mfa_token is a short-lived token issued after password validation
        $userId = \Illuminate\Support\Facades\Cache::get("mfa_pending_{$request->input('mfa_token')}");

        if (!$userId) {
            return $this->errorResponse(
                message: __('MFA session expired. Please log in again.'),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        $user = User::findOrFail($userId);

        $verified = false;

        if ($request->filled('code')) {
            $verified = $this->mfaService->verifyMfaChallenge($user, $request->input('code'), $request);
        } elseif ($request->filled('recovery_code')) {
            $verified = $this->mfaService->verifyRecoveryCode($user, $request->input('recovery_code'), $request);
        }

        if (!$verified) {
            return $this->errorResponse(
                message: __('Invalid verification code'),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        // Clean up pending MFA session
        \Illuminate\Support\Facades\Cache::forget("mfa_pending_{$request->input('mfa_token')}");

        // Complete login
        $loginResponse = $this->loginService->authenticateOAuthUser(
            $user,
            $request->input('account_id') ? (int)$request->input('account_id') : null,
        );

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
