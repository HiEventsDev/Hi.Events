<?php

namespace HiEvents\Enterprise\Sso\Exceptions;

use Exception;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SsoRequiredException extends Exception
{
    public function __construct(
        private readonly ?string $email = null,
    ) {
        parent::__construct(__('Your organization requires you to sign in with single sign-on.'));
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error_code' => SsoErrorCode::SSO_REQUIRED->value,
            'context' => (object) array_filter(['email' => $this->email]),
        ], Response::HTTP_FORBIDDEN);
    }
}
