<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\Auth\Sso\StartSsoLinkRequest;
use HiEvents\Services\Application\Handlers\Auth\Sso\StartSsoLinkHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[AllowedForReadOnlyUsers]
class StartSsoLinkAction extends BaseSsoAction
{
    public function __construct(
        private readonly StartSsoLinkHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(StartSsoLinkRequest $request, string $provider): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->signInMethodsImpersonationResponse();
        }

        try {
            $result = $this->handler->handle(
                user: $this->getAuthenticatedUser(),
                providerKey: $provider,
                tokenId: (string) $this->getAuthTokenId(),
                password: $request->validated('password'),
                reauthProof: $request->validated('reauth_proof'),
                returnPath: $request->validated('return_path'),
            );
        } catch (IdentityConfirmationFailedException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->startResponse($result);
    }
}
