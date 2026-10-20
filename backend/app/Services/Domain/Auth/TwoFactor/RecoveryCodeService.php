<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    public const CODE_COUNT = 10;

    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const SEGMENT_LENGTH = 5;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @return string[]
     */
    public function regenerate(int $userId): array
    {
        $codes = array_map(fn () => $this->generateCode(), range(1, self::CODE_COUNT));

        $this->userRepository->updateWhere(
            attributes: [
                'two_factor_recovery_codes' => json_encode(array_map($this->hash(...), $codes)),
            ],
            where: ['id' => $userId],
        );

        return $codes;
    }

    public function consume(UserDomainObject $user, string $code): bool
    {
        $storedValue = $user->getTwoFactorRecoveryCodes();
        $hashes = $this->decode($storedValue);
        $hash = $this->hash($code);

        $index = array_search($hash, $hashes, true);

        if ($index === false) {
            return false;
        }

        unset($hashes[$index]);

        $updated = $this->userRepository->updateWhere(
            attributes: ['two_factor_recovery_codes' => json_encode(array_values($hashes))],
            where: [
                'id' => $user->getId(),
                'two_factor_recovery_codes' => $storedValue,
            ],
        );

        return $updated === 1;
    }

    public function remaining(UserDomainObject $user): int
    {
        return count($this->decode($user->getTwoFactorRecoveryCodes()));
    }

    private function normalise(string $code): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower($code));
    }

    private function hash(string $code): string
    {
        return hash('sha256', $this->normalise($code));
    }

    /**
     * @return string[]
     */
    private function decode(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function generateCode(): string
    {
        $characters = '';
        $alphabetLength = strlen(self::ALPHABET);

        for ($i = 0; $i < self::SEGMENT_LENGTH * 2; $i++) {
            $characters .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return substr($characters, 0, self::SEGMENT_LENGTH).'-'.substr($characters, self::SEGMENT_LENGTH);
    }
}
