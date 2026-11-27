<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Identity;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;

class DeletedContactHasher
{
    private const RECIPIENT_PLACEHOLDER_PREFIX = 'deleted:';

    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
    ) {}

    public function salt(int $organizerId): string
    {
        return $this->contactRepository->findOrCreateDeletedContactSalt($organizerId);
    }

    public function hash(int $organizerId, string $normalizedEmail): string
    {
        return self::hashWithSalt($this->salt($organizerId), $normalizedEmail);
    }

    public static function hashWithSalt(string $salt, string $normalizedEmail): string
    {
        return hash('sha256', $salt.$normalizedEmail);
    }

    public static function recipientPlaceholder(string $emailHash): string
    {
        return self::RECIPIENT_PLACEHOLDER_PREFIX.$emailHash;
    }

    public static function isRecipientPlaceholderSql(string $emailExpression): string
    {
        return "$emailExpression LIKE '".self::RECIPIENT_PLACEHOLDER_PREFIX."%'";
    }

    public static function hashFromRecipientPlaceholderSql(string $emailExpression): string
    {
        return "substr($emailExpression, ".(strlen(self::RECIPIENT_PLACEHOLDER_PREFIX) + 1).')';
    }

    public static function hashSql(string $saltPlaceholder, string $emailExpression): string
    {
        return "encode(sha256(convert_to($saltPlaceholder || $emailExpression, 'UTF8')), 'hex')";
    }
}
