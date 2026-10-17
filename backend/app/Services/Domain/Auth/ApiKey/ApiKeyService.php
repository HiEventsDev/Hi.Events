<?php

namespace HiEvents\Services\Domain\Auth\ApiKey;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\User\ApiKeyCreatedMail;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use HiEvents\Services\Domain\Auth\ApiKey\DTO\CreatedApiKeyDTO;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Str;

class ApiKeyService
{
    public const MAX_ACTIVE_KEYS_PER_MEMBER = 25;

    public const EXPIRY_OPTIONS_IN_DAYS = [7, 30, 60, 90, 180, 365];

    public function __construct(
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Mailer $mailer,
        private readonly Config $config,
    ) {}

    public function create(
        UserDomainObject $user,
        int $accountId,
        string $name,
        ApiKeyAccess $access,
        ?int $expiresInDays,
    ): CreatedApiKeyDTO {
        $plainTextKey = $this->generateKey();
        $expiresAt = $expiresInDays === null ? null : CarbonImmutable::now()->addDays($expiresInDays);

        /** @var PersonalAccessTokenDomainObject $apiKey */
        $apiKey = $this->apiKeyRepository->create([
            PersonalAccessTokenDomainObject::TOKENABLE_TYPE => User::class,
            PersonalAccessTokenDomainObject::TOKENABLE_ID => $user->getId(),
            PersonalAccessTokenDomainObject::ACCOUNT_ID => $accountId,
            PersonalAccessTokenDomainObject::NAME => $name,
            PersonalAccessTokenDomainObject::TOKEN => hash('sha256', $plainTextKey),
            PersonalAccessTokenDomainObject::TOKEN_HINT => substr($plainTextKey, -4),
            PersonalAccessTokenDomainObject::ABILITIES => json_encode($access->abilities(), JSON_THROW_ON_ERROR),
            PersonalAccessTokenDomainObject::EXPIRES_AT => $expiresAt?->toDateTimeString(),
        ]);

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new ApiKeyCreatedMail(
                keyName: $name,
                accountName: $this->accountRepository->findById($accountId)->getName(),
                access: $access,
                expiresAt: $expiresAt?->toDateString(),
            ));

        return new CreatedApiKeyDTO(apiKey: $apiKey->setUser($user), plainTextKey: $plainTextKey);
    }

    public function countActiveKeys(int $userId, int $accountId): int
    {
        return $this->apiKeyRepository->countWhere([
            PersonalAccessTokenDomainObject::TOKENABLE_ID => $userId,
            PersonalAccessTokenDomainObject::ACCOUNT_ID => $accountId,
            static fn ($query) => $query
                ->whereNull(PersonalAccessTokenDomainObject::EXPIRES_AT)
                ->orWhere(PersonalAccessTokenDomainObject::EXPIRES_AT, '>', now()),
        ]);
    }

    public function revokeAllForMember(int $userId, int $accountId): void
    {
        $this->apiKeyRepository->deleteWhere([
            PersonalAccessTokenDomainObject::TOKENABLE_ID => $userId,
            PersonalAccessTokenDomainObject::ACCOUNT_ID => $accountId,
        ]);
    }

    public function isUsableBy(int $userId, int $accountId): bool
    {
        return $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            'account_id' => $accountId,
            'status' => UserStatus::ACTIVE->name,
        ]) !== null;
    }

    private function generateKey(): string
    {
        $entropy = Str::random(40);

        return $this->config->get('sanctum.token_prefix').$entropy.hash('crc32b', $entropy);
    }
}
