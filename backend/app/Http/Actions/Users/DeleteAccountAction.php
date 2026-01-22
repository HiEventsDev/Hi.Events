<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\User\DeleteAccountRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use HiEvents\Services\Domain\User\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use RuntimeException;

class DeleteAccountAction extends BaseAction
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    )
    {
    }

    public function __invoke(DeleteAccountRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse(__('Unauthenticated.'), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        try {
            $this->accountDeletionService->deleteUserAccount(
                user: $user,
                confirmationWord: (string)$request->validated('confirmation'),
                password: (string)$request->validated('password'),
            );
        } catch (RuntimeException $e) {
            $statusCode = $e->getCode();
            $message = $e->getMessage() ?: __('Unable to delete account.');

            // Validation-like errors
            if ($statusCode === ResponseCodes::HTTP_UNPROCESSABLE_ENTITY) {
                $errors = [];
                if (str_contains(strtolower($message), 'delete')) {
                    $errors['confirmation'] = $message;
                }
                if (str_contains(strtolower($message), 'password')) {
                    $errors['password'] = $message;
                }

                return $this->errorResponse(
                    message: $message,
                    statusCode: ResponseCodes::HTTP_UNPROCESSABLE_ENTITY,
                    errors: $errors,
                );
            }

            // Default to 409 conflict for ownership blocks or generic 400.
            return $this->errorResponse(
                message: $message,
                statusCode: in_array($statusCode, [ResponseCodes::HTTP_CONFLICT, ResponseCodes::HTTP_FORBIDDEN], true)
                    ? $statusCode
                    : ResponseCodes::HTTP_BAD_REQUEST,
            );
        }

        Auth::logout();

        return $this->jsonResponse([
            'message' => __('Account deleted successfully.'),
        ], ResponseCodes::HTTP_OK)->withCookie(Cookie::forget('token'));
    }
}
