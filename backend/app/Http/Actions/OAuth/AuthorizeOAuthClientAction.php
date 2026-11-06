<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\OAuth\StartOAuthAuthorizationHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthorizeOAuthClientAction extends BaseAction
{
    public function __construct(
        private readonly StartOAuthAuthorizationHandler $handler,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        return $this->redirectResponse($this->handler->handle($request->query()));
    }
}
