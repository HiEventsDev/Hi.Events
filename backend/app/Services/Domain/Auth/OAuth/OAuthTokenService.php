<?php

namespace HiEvents\Services\Domain\Auth\OAuth;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\DomainObjects\Enums\OAuthScope;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\DomainObjects\OauthConnectionDomainObject;
use HiEvents\DomainObjects\OauthRefreshTokenDomainObject;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\Exceptions\OAuthException;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\OauthConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\OauthRefreshTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationCodeDTO;
use HiEvents\Services\Domain\Auth\OAuth\DTO\OAuthTokensDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class OAuthTokenService
{
    public const ACCESS_TOKEN_TTL_SECONDS = 3600;

    public const REFRESH_TOKEN_TTL_DAYS = 90;

    public const REFRESH_REUSE_GRACE_SECONDS = 30;

    public const MAX_CONNECTIONS_PER_MEMBER = 25;

    public function __construct(
        private readonly OauthConnectionRepositoryInterface $connectionRepository,
        private readonly OauthRefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly PersonalAccessTokenRepositoryInterface $accessTokenRepository,
        private readonly ApiKeyService $apiKeyService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws OAuthException
     */
    public function connect(OauthClientDomainObject $client, AuthorizationCodeDTO $code): OauthConnectionDomainObject
    {
        $this->connectionRepository->deleteWhere([
            'oauth_client_id' => $client->getId(),
            'user_id' => $code->user_id,
            'account_id' => $code->account_id,
        ]);

        if ($this->hasReachedConnectionLimit($code->user_id, $code->account_id, $client->getId())) {
            throw new OAuthException(OAuthErrorCode::ACCESS_DENIED, self::connectionLimitMessage());
        }

        /** @var OauthConnectionDomainObject $connection */
        $connection = $this->connectionRepository->create([
            'oauth_client_id' => $client->getId(),
            'user_id' => $code->user_id,
            'account_id' => $code->account_id,
            'access' => $code->access->value,
            'last_used_at' => now()->toDateTimeString(),
        ]);

        return $connection->setOauthClient($client);
    }

    public function hasReachedConnectionLimit(int $userId, int $accountId, int $clientId): bool
    {
        return $this->connectionRepository->countWhere([
            'user_id' => $userId,
            'account_id' => $accountId,
            ['oauth_client_id', '!=', $clientId],
        ]) >= self::MAX_CONNECTIONS_PER_MEMBER;
    }

    public static function connectionLimitMessage(): string
    {
        return __(
            'You can have up to :max connected apps. Disconnect one you no longer use and try again.',
            ['max' => self::MAX_CONNECTIONS_PER_MEMBER],
        );
    }

    public function issueTokens(OauthConnectionDomainObject $connection, OauthClientDomainObject $client): OAuthTokensDTO
    {
        $access = $connection->getAccessLevel();
        $accessToken = $this->apiKeyService->generateKey();
        $refreshToken = 'hier_'.Str::random(64);

        $this->accessTokenRepository->create([
            PersonalAccessTokenDomainObject::TOKENABLE_TYPE => User::class,
            PersonalAccessTokenDomainObject::TOKENABLE_ID => $connection->getUserId(),
            PersonalAccessTokenDomainObject::ACCOUNT_ID => $connection->getAccountId(),
            PersonalAccessTokenDomainObject::OAUTH_CONNECTION_ID => $connection->getId(),
            PersonalAccessTokenDomainObject::NAME => $client->getName(),
            PersonalAccessTokenDomainObject::TOKEN => hash('sha256', $accessToken),
            PersonalAccessTokenDomainObject::TOKEN_HINT => substr($accessToken, -4),
            PersonalAccessTokenDomainObject::ABILITIES => json_encode($access->abilities(), JSON_THROW_ON_ERROR),
            PersonalAccessTokenDomainObject::EXPIRES_AT => now()->addSeconds(self::ACCESS_TOKEN_TTL_SECONDS)->toDateTimeString(),
        ]);

        $this->refreshTokenRepository->create([
            'oauth_connection_id' => $connection->getId(),
            'token_hash' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS)->toDateTimeString(),
        ]);

        return new OAuthTokensDTO(
            access_token: $accessToken,
            refresh_token: $refreshToken,
            expires_in: self::ACCESS_TOKEN_TTL_SECONDS,
            scope: OAuthScope::forAccess($access),
        );
    }

    /**
     * @throws OAuthException
     */
    public function refresh(string $refreshToken, OauthClientDomainObject $client): OAuthTokensDTO
    {
        /** @var OauthRefreshTokenDomainObject|null $stored */
        $stored = $this->refreshTokenRepository->findFirstWhere(['token_hash' => hash('sha256', $refreshToken)]);

        if ($stored === null || CarbonImmutable::parse($stored->getExpiresAt())->isPast()) {
            throw $this->invalidGrant();
        }

        /** @var OauthConnectionDomainObject|null $connection */
        $connection = $this->connectionRepository->findFirstWhere(['id' => $stored->getOauthConnectionId()]);

        if ($connection === null || $connection->getOauthClientId() !== $client->getId()) {
            throw $this->invalidGrant();
        }

        if ($stored->getRotatedAt() !== null) {
            if (CarbonImmutable::parse($stored->getRotatedAt())->addSeconds(self::REFRESH_REUSE_GRACE_SECONDS)->isPast()) {
                $this->revokeConnection($connection->getId());
            }

            throw $this->invalidGrant();
        }

        if (! $this->apiKeyService->isUsableBy($connection->getUserId(), $connection->getAccountId())) {
            $this->revokeConnection($connection->getId());

            throw $this->invalidGrant();
        }

        return $this->databaseManager->transaction(function () use ($stored, $connection, $client) {
            $rotated = $this->refreshTokenRepository->updateWhere(
                ['rotated_at' => now()],
                ['id' => $stored->getId(), ['rotated_at', 'null', null]],
            );

            if ($rotated === 0) {
                throw $this->invalidGrant();
            }

            $this->accessTokenRepository->deleteWhere([
                PersonalAccessTokenDomainObject::OAUTH_CONNECTION_ID => $connection->getId(),
                [PersonalAccessTokenDomainObject::EXPIRES_AT, '<', now()],
            ]);

            $this->connectionRepository->updateWhere(['last_used_at' => now()], ['id' => $connection->getId()]);

            return $this->issueTokens($connection, $client);
        });
    }

    public function revokeByToken(string $token): void
    {
        /** @var OauthRefreshTokenDomainObject|null $refreshToken */
        $refreshToken = $this->refreshTokenRepository->findFirstWhere(['token_hash' => hash('sha256', $token)]);

        if ($refreshToken !== null) {
            $this->revokeConnection($refreshToken->getOauthConnectionId());

            return;
        }

        /** @var PersonalAccessTokenDomainObject|null $accessToken */
        $accessToken = $this->accessTokenRepository->findFirstWhere([
            PersonalAccessTokenDomainObject::TOKEN => hash('sha256', $token),
            [PersonalAccessTokenDomainObject::OAUTH_CONNECTION_ID, 'not null', null],
        ]);

        if ($accessToken?->getOauthConnectionId() !== null) {
            $this->revokeConnection($accessToken->getOauthConnectionId());
        }
    }

    public function revokeConnection(int $connectionId): void
    {
        $this->connectionRepository->deleteWhere(['id' => $connectionId]);
    }

    private function invalidGrant(): OAuthException
    {
        return new OAuthException(OAuthErrorCode::INVALID_GRANT, __('The refresh token is invalid or has expired. Connect again.'));
    }
}
