<?php

namespace HiEvents\Services\Domain\Auth\ApiKey;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\Mail\User\ApiKeyExpiringMail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;

class ApiKeyExpiryReminderService
{
    public const REMINDER_WINDOW_DAYS = 7;

    public const MINIMUM_LIFETIME_DAYS = 14;

    public function __construct(
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly ApiKeyService $apiKeyService,
        private readonly Mailer $mailer,
    ) {}

    public function sendDueReminders(): void
    {
        $now = CarbonImmutable::now();
        $apiKeys = $this->apiKeyRepository->findDueForExpiryReminder($now, self::REMINDER_WINDOW_DAYS, self::MINIMUM_LIFETIME_DAYS);

        if ($apiKeys->isEmpty()) {
            return;
        }

        $accountNames = $this->accountRepository
            ->findWhereIn('id', $apiKeys->map(fn (PersonalAccessTokenDomainObject $apiKey) => $apiKey->getAccountId())->unique()->values()->all())
            ->mapWithKeys(fn (AccountDomainObject $account) => [$account->getId() => $account->getName()]);

        foreach ($apiKeys as $apiKey) {
            $user = $apiKey->getUser();

            if ($user !== null
                && $accountNames->has($apiKey->getAccountId())
                && $this->apiKeyService->isUsableBy($user->getId(), $apiKey->getAccountId())) {
                $this->mailer
                    ->to($user->getEmail())
                    ->locale($user->getLocale())
                    ->send(new ApiKeyExpiringMail(
                        keyName: $apiKey->getName(),
                        accountName: $accountNames->get($apiKey->getAccountId()),
                        expiresAt: CarbonImmutable::parse($apiKey->getExpiresAt())->toDateString(),
                    ));
            }

            $this->apiKeyRepository->updateWhere(
                attributes: [PersonalAccessTokenDomainObject::EXPIRY_REMINDER_SENT_AT => $now->toDateTimeString()],
                where: [PersonalAccessTokenDomainObject::ID => $apiKey->getId()],
            );
        }
    }
}
