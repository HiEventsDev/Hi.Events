<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\ResetPasswordDTO;
use HiEvents\Http\Request\Auth\ResetPasswordRequest;
use HiEvents\Service\Handler\Auth\ResetPasswordHandler;

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
                currentPassword: $request->validated('current_password'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (PasswordInvalidException $exception) {
            throw ValidationException::withMessages([
                'current_password' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse(
            data: [
                'message' => __('Your password has been reset. Please login with your new password.'),
            ]
        );
    }
}
