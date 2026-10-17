<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\Auth\Sso\ExchangeSsoCodeRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\ExchangeSsoCodeDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\ExchangeSsoCodeHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

#[AllowedForReadOnlyUsers]
class ExchangeAuthenticatedSsoCodeAction extends BaseSsoAction
{
    public function __construct(
        private readonly ExchangeSsoCodeHandler $handler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(ExchangeSsoCodeRequest $request): JsonResponse
    {
        if ($this->isImpersonating()) {
            return $this->signInMethodsImpersonationResponse();
        }

        try {
            $result = $this->handler->handle(
                new ExchangeSsoCodeDTO(
                    code: $request->validated('code'),
                    state: $request->validated('state'),
                    binding: $request->validated('binding'),
                    authenticatedUserId: $this->getAuthenticatedUser()->getId(),
                    tokenId: $this->getAuthTokenId(),
                ),
                [SsoIntent::LINK, SsoIntent::REAUTH, SsoIntent::TEST],
            );
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->exchangeResponse($result);
    }
}
