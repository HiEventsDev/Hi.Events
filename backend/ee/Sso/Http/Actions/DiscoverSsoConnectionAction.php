<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\Enterprise\Sso\Http\Request\DiscoverSsoConnectionRequest;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\DiscoverSsoConnectionHandler;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Actions\Auth\Sso\BaseSsoAction;
use Illuminate\Http\JsonResponse;

class DiscoverSsoConnectionAction extends BaseSsoAction
{
    public function __construct(
        private readonly DiscoverSsoConnectionHandler $handler,
    ) {}

    public function __invoke(DiscoverSsoConnectionRequest $request): JsonResponse
    {
        try {
            $provider = $this->handler->handle($request->validated('email'));
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->jsonResponse([
            'key' => $provider->key,
            'name' => $provider->name,
            'type' => $provider->type->value,
            'logo_url' => $provider->logoUrl,
        ], wrapInData: true);
    }
}
