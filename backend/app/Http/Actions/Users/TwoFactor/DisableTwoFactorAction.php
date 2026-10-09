<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\TwoFactor\DisableTwoFactorRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DisableTwoFactorHandler;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DTO\DisableTwoFactorDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DisableTwoFactorAction extends BaseAction
{
    public function __construct(
        private readonly DisableTwoFactorHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(DisableTwoFactorRequest $request): Response|JsonResponse
    {
        $usingRecoveryCode = $request->filled('recovery_code');

        try {
            $this->handler->handle(new DisableTwoFactorDTO(
                userId: $this->getAuthenticatedUser()->getId(),
                password: $request->validated('password'),
                code: $usingRecoveryCode ? null : $request->validated('code'),
                recoveryCode: $usingRecoveryCode ? $request->validated('recovery_code') : null,
            ));
        } catch (PasswordInvalidException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        } catch (TwoFactorLockedOutException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_TOO_MANY_REQUESTS);
        } catch (InvalidTwoFactorCodeException $exception) {
            throw ValidationException::withMessages([
                $usingRecoveryCode ? 'recovery_code' : 'code' => $exception->getMessage(),
            ]);
        } catch (TwoFactorRequiredByAccountException|ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
