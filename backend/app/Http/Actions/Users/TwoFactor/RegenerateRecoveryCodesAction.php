<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\TwoFactor\TwoFactorCodeRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\User\TwoFactor\RegenerateRecoveryCodesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegenerateRecoveryCodesAction extends BaseAction
{
    public function __construct(
        private readonly RegenerateRecoveryCodesHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(TwoFactorCodeRequest $request): JsonResponse
    {
        try {
            $recoveryCodes = $this->handler->handle(
                userId: $this->getAuthenticatedUser()->getId(),
                code: $request->validated('code'),
            );
        } catch (TwoFactorLockedOutException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_TOO_MANY_REQUESTS);
        } catch (InvalidTwoFactorCodeException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->jsonResponse([
            /** @var array<int, string> */
            'recovery_codes' => $recoveryCodes,
        ], wrapInData: true);
    }
}
