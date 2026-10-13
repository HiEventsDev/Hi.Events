<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use HiEvents\Helper\Url;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class LifecycleEmailUnsubscribeTokens
{
    private const PURPOSE = 'lifecycle-email-unsubscribe';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function tokenFor(int $userId): string
    {
        return $userId.'.'.$this->signature($userId);
    }

    public function userIdFrom(string $token): ?int
    {
        if (preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $matches) !== 1) {
            return null;
        }

        $userId = (int) $matches[1];

        return hash_equals($this->signature($userId), $matches[2]) ? $userId : null;
    }

    public function pageUrlFor(int $userId): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::LIFECYCLE_EMAIL_UNSUBSCRIBE), $this->tokenFor($userId));
    }

    public function oneClickUrlFor(int $userId): string
    {
        return Url::getApiUrl('public/lifecycle-emails/unsubscribe/'.$this->tokenFor($userId));
    }

    private function signature(int $userId): string
    {
        return hash_hmac('sha256', self::PURPOSE.':'.$userId, (string) $this->config->get('app.key'));
    }
}
