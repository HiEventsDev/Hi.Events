<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\Exceptions\OAuthException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ApiKey\CreateApiKeyRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\OAuth\RegisterOAuthClientHandler;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterOAuthClientAction extends BaseAction
{
    use OAuthResponses;

    private const MAX_NAME_LENGTH = 100;

    public function __construct(
        private readonly RegisterOAuthClientHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->validateMetadata($request);

            $client = $this->handler->handle(
                $this->clientName($request->input('client_name')),
                $request->input('redirect_uris'),
            );
        } catch (OAuthException $exception) {
            return $this->oauthErrorResponse($exception);
        }

        return $this->noStore($this->jsonResponse([
            'client_id' => $client->getClientId(),
            'client_id_issued_at' => now()->timestamp,
            'client_name' => $client->getName(),
            'redirect_uris' => $client->getRedirectUris(),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], ResponseCodes::HTTP_CREATED));
    }

    /**
     * @throws OAuthException
     */
    private function validateMetadata(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'redirect_uris' => ['required', 'array', 'min:1', 'max:'.OAuthRedirectUriPolicy::MAX_REDIRECT_URIS],
            'redirect_uris.*' => ['required', 'string', 'max:2048'],
            'client_name' => ['nullable', 'string', 'max:500'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array'],
            'grant_types.*' => ['in:authorization_code,refresh_token'],
            'response_types' => ['nullable', 'array'],
            'response_types.*' => ['in:code'],
        ]);

        if ($validator->fails()) {
            $redirectUriFailed = collect($validator->errors()->keys())->contains(fn (string $key) => str_starts_with($key, 'redirect_uris'));

            throw new OAuthException(
                $redirectUriFailed ? OAuthErrorCode::INVALID_REDIRECT_URI : OAuthErrorCode::INVALID_CLIENT_METADATA,
                $validator->errors()->first(),
            );
        }
    }

    private function clientName(mixed $name): string
    {
        $allowed = trim((string) preg_replace("/[^\pL\pN\s\-_.,:'&()\/]/u", '', is_string($name) ? $name : ''));
        $allowed = ltrim(mb_substr($allowed, 0, self::MAX_NAME_LENGTH), "-_.,:'&()/ ");

        return preg_match(CreateApiKeyRequest::NAME_PATTERN, $allowed) === 1 ? $allowed : __('Unnamed app');
    }
}
