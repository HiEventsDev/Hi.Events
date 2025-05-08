<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\InvalidPasswordResetTokenException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Auth\ResetPasswordRequest;
use HiEvents\Services\Application\Handlers\Auth\DTO\ResetPasswordDTO;
use HiEvents\Services\Application\Handlers\Auth\ResetPasswordHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class ResetPasswordAction extends BaseAction
{
    private ResetPasswordHandler $resetPasswordHandler;

    public function __construct(ResetPasswordHandler $resetPasswordHandler)
    {
        $this->resetPasswordHandler = $resetPasswordHandler;
    }

    /**
     * @throws ResourceNotFoundException|Throwable
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->resetPasswordHandler->handle(new ResetPasswordDTO(
                token: $request->route('reset_token'),
                password: $request->validated('password'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (PasswordInvalidException $exception) {
            throw ValidationException::withMessages([
                'current_password' => $exception->getMessage(),
            ]);
        } catch (InvalidPasswordResetTokenException $e) {
            throw new ResourceNotFoundException($e->getMessage());
        }

        return $this->jsonResponse(
            data: [
                'message' => __('Your password has been reset. Please login with your new password.'),
            ]
        );
    }
}
