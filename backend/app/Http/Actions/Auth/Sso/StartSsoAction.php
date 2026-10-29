<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Request\Auth\Sso\StartSsoRequest;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\StartSsoDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\StartSsoHandler;
use Illuminate\Http\JsonResponse;

class StartSsoAction extends BaseSsoAction
{
    public function __construct(
        private readonly StartSsoHandler $handler,
    ) {}

    public function __invoke(StartSsoRequest $request, string $provider): JsonResponse
    {
        try {
            $result = $this->handler->handle(new StartSsoDTO(
                providerKey: $provider,
                intent: SsoIntent::from($request->validated('intent')),
                returnPath: $request->validated('return_path'),
                invitationToken: $request->validated('invitation_token'),
                timezone: $request->validated('timezone'),
                marketingOptIn: (bool) $request->validated('marketing_opt_in'),
                switchAccount: (bool) $request->validated('switch_account'),
                loginHint: $request->validated('login_hint'),
                silent: (bool) $request->validated('silent'),
            ));
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->startResponse($result);
    }
}
