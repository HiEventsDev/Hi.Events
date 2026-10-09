<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\TwoFactorChallengeExpiredException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\TwoFactorLoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\DTO\TwoFactorLoginDTO;
use HiEvents\Services\Application\Handlers\Auth\TwoFactorLoginHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TwoFactorLoginAction extends BaseAuthAction
{
    public const CHALLENGE_EXPIRED_ERROR_CODE = 'TWO_FACTOR_CHALLENGE_EXPIRED';

    public function __construct(
        private readonly TwoFactorLoginHandler $twoFactorLoginHandler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(TwoFactorLoginRequest $request): JsonResponse
    {
        $usingRecoveryCode = $request->filled('recovery_code');

        try {
            $loginResponse = $this->twoFactorLoginHandler->handle(new TwoFactorLoginDTO(
                challengeToken: $request->validated('challenge_token'),
                code: $usingRecoveryCode ? null : $request->validated('code'),
                recoveryCode: $usingRecoveryCode ? $request->validated('recovery_code') : null,
                accountId: $request->validated('account_id') !== null ? (int) $request->validated('account_id') : null,
                rememberDevice: (bool) $request->validated('remember_device'),
                userAgent: $request->userAgent(),
                ipAddress: $request->ip(),
            ));
        } catch (InvalidTwoFactorCodeException $exception) {
            throw ValidationException::withMessages([
                $usingRecoveryCode ? 'recovery_code' : 'code' => $exception->getMessage(),
            ]);
        } catch (TwoFactorChallengeExpiredException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => self::CHALLENGE_EXPIRED_ERROR_CODE,
            ], ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        $response = $this->respondWithToken(
            token: $loginResponse->token,
            accounts: $loginResponse->accounts,
            user: $loginResponse->user,
            recoveryCodesRemaining: $loginResponse->recoveryCodesRemaining,
        );

        $this->addTrustedDeviceCookie($response, $loginResponse->trustedDeviceToken);

        return $response;
    }
}
