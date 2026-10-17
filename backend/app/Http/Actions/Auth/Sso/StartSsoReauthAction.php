<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\Auth\Sso\StartSsoReauthRequest;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\StartSsoDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\StartSsoHandler;
use Illuminate\Http\JsonResponse;

#[AllowedForReadOnlyUsers]
class StartSsoReauthAction extends BaseSsoAction
{
    public function __construct(
        private readonly StartSsoHandler $handler,
    ) {}

    public function __invoke(StartSsoReauthRequest $request, string $provider): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->signInMethodsImpersonationResponse();
        }

        try {
            $result = $this->handler->handle(new StartSsoDTO(
                providerKey: $provider,
                intent: SsoIntent::REAUTH,
                returnPath: $request->validated('return_path'),
                userId: $this->getAuthenticatedUser()->getId(),
                tokenId: $this->getAuthTokenId(),
            ));
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->startResponse($result);
    }
}
