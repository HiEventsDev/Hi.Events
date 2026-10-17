<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\Auth\Sso\ConfirmIdentityRequest;
use HiEvents\Services\Application\Handlers\Auth\Sso\DeleteSsoIdentityHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
class DeleteSsoIdentityAction extends BaseSsoAction
{
    public function __construct(
        private readonly DeleteSsoIdentityHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(ConfirmIdentityRequest $request, int $identityId): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->signInMethodsImpersonationResponse();
        }

        $user = $this->getAuthenticatedUser();

        try {
            $this->handler->handle(
                user: $user,
                identityId: $identityId,
                password: $request->validated('password'),
                reauthProof: $request->validated('reauth_proof'),
                tokenId: $this->getAuthTokenId(),
            );
        } catch (IdentityConfirmationFailedException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->respondWithToken($this->issueFreshTokenForCurrentSession(), collect(), $user);
    }
}
