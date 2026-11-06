<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Exceptions\OAuthException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\OAuth\IssueOAuthTokenHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueOAuthTokenAction extends BaseAction
{
    use OAuthResponses;

    public function __construct(
        private readonly IssueOAuthTokenHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $tokens = $this->handler->handle($request->all());
        } catch (OAuthException $exception) {
            return $this->oauthErrorResponse($exception);
        }

        return $this->noStore($this->jsonResponse($tokens->toTokenResponse()));
    }
}
