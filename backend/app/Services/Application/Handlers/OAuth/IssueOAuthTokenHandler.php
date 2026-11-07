<?php

namespace HiEvents\Services\Application\Handlers\OAuth;

use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\Exceptions\OAuthException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\User\OAuthAppConnectedMail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationCodeDTO;
use HiEvents\Services\Domain\Auth\OAuth\DTO\OAuthTokensDTO;
use HiEvents\Services\Domain\Auth\OAuth\OAuthAuthorizationStore;
use HiEvents\Services\Domain\Auth\OAuth\OAuthEndpoints;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use HiEvents\Services\Domain\Auth\OAuth\OAuthTokenService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class IssueOAuthTokenHandler
{
    private const CODE_VERIFIER_PATTERN = '/^[A-Za-z0-9\-._~]{43,128}$/';

    public function __construct(
        private readonly OauthClientRepositoryInterface $clientRepository,
        private readonly OAuthAuthorizationStore $store,
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthEndpoints $endpoints,
        private readonly OAuthRedirectUriPolicy $redirectUriPolicy,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Mailer $mailer,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws OAuthException
     */
    public function handle(array $input): OAuthTokensDTO
    {
        $client = $this->client($input['client_id'] ?? null);

        return match ($input['grant_type'] ?? null) {
            'authorization_code' => $this->exchangeCode($client, $input),
            'refresh_token' => $this->tokenService->refresh($this->requiredString($input, 'refresh_token'), $client),
            default => throw new OAuthException(OAuthErrorCode::UNSUPPORTED_GRANT_TYPE, __('Use the authorization_code or refresh_token grant.')),
        };
    }

    /**
     * @throws OAuthException
     */
    private function exchangeCode(OauthClientDomainObject $client, array $input): OAuthTokensDTO
    {
        $secret = $this->requiredString($input, 'code');
        $redirectUri = $this->requiredString($input, 'redirect_uri');
        $verifier = $this->requiredString($input, 'code_verifier');
        $code = $this->store->consumeCode($secret);

        if ($code === null) {
            $replayedConnectionId = $this->store->connectionForReplayedCode($secret);

            if ($replayedConnectionId !== null) {
                $this->tokenService->revokeConnection($replayedConnectionId);
            }

            throw $this->invalidGrant();
        }

        if ($code->client_id !== $client->getClientId() || $code->redirect_uri !== $redirectUri || ! $this->verifierMatches($verifier, $code)) {
            throw $this->invalidGrant();
        }

        $resource = is_string($input['resource'] ?? null) ? $input['resource'] : null;

        if (! $this->endpoints->isResource($resource)) {
            throw new OAuthException(OAuthErrorCode::INVALID_TARGET, __('This server only issues tokens for its MCP endpoint.'));
        }

        [$connection, $tokens] = $this->databaseManager->transaction(function () use ($client, $code) {
            $connection = $this->tokenService->connect($client, $code);

            return [$connection, $this->tokenService->issueTokens($connection, $client)];
        });

        $this->store->rememberConnectionForCode($secret, $connection->getId());
        $this->sendConnectedMail($client, $code);

        return $tokens;
    }

    private function sendConnectedMail(OauthClientDomainObject $client, AuthorizationCodeDTO $code): void
    {
        $user = $this->userRepository->findById($code->user_id);

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new OAuthAppConnectedMail(
                appName: $client->getName(),
                redirectHost: $this->redirectUriPolicy->describe($code->redirect_uri),
                accountName: $this->accountRepository->findById($code->account_id)->getName(),
                access: $code->access,
            ));
    }

    private function verifierMatches(string $verifier, AuthorizationCodeDTO $code): bool
    {
        if (preg_match(self::CODE_VERIFIER_PATTERN, $verifier) !== 1) {
            return false;
        }

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($code->code_challenge, $challenge);
    }

    /**
     * @throws OAuthException
     */
    private function client(mixed $clientId): OauthClientDomainObject
    {
        /** @var OauthClientDomainObject|null $client */
        $client = is_string($clientId) && $clientId !== ''
            ? $this->clientRepository->findFirstWhere(['client_id' => $clientId])
            : null;

        if ($client === null) {
            throw new OAuthException(OAuthErrorCode::INVALID_CLIENT, __('Unknown client.'), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $client;
    }

    /**
     * @throws OAuthException
     */
    private function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new OAuthException(OAuthErrorCode::INVALID_REQUEST, __('The :field parameter is required.', ['field' => $key]));
        }

        return $value;
    }

    private function invalidGrant(): OAuthException
    {
        return new OAuthException(OAuthErrorCode::INVALID_GRANT, __('The authorization code is invalid or has expired.'));
    }
}
