<?php

namespace HiEvents\Services\Domain\SelfService;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\SelfServiceVerificationDomainObjectAbstract;
use HiEvents\DomainObjects\SelfServiceVerificationDomainObject;
use HiEvents\Exceptions\SelfServiceVerificationException;
use HiEvents\Helper\EmailMasker;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\SelfServiceVerificationRepositoryInterface;
use HiEvents\Services\Domain\SelfService\DTO\SelfServiceSessionDTO;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Config\Repository;

class SelfServiceVerificationService
{
    public const SESSION_HEADER = 'X-Self-Service-Token';

    public const CODE_LENGTH = 6;

    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const MAX_FAILED_CODES_PER_ADDRESS = 10;

    public const FAILED_CODES_LOCK_MINUTES = 15;

    public const SESSION_TTL_MINUTES = 30;

    public function __construct(
        private readonly SelfServiceVerificationRepositoryInterface $verificationRepository,
        private readonly TokenGeneratorService $tokenGeneratorService,
        private readonly Repository $config,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function issueCode(string $email): string
    {
        $email = $this->normalise($email);
        $code = str_pad((string) random_int(0, (10 ** self::CODE_LENGTH) - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $this->verificationRepository->deleteWhere([
            SelfServiceVerificationDomainObjectAbstract::EMAIL => $email,
            [SelfServiceVerificationDomainObjectAbstract::SESSION_TOKEN_HASH, 'null', null],
        ]);

        $this->verificationRepository->create([
            SelfServiceVerificationDomainObjectAbstract::EMAIL => $email,
            SelfServiceVerificationDomainObjectAbstract::CODE_HASH => $this->hash($code, $email),
            SelfServiceVerificationDomainObjectAbstract::CODE_EXPIRES_AT => Carbon::now()->addMinutes(self::CODE_TTL_MINUTES)->toDateTimeString(),
        ]);

        return $code;
    }

    /**
     * @throws SelfServiceVerificationException
     */
    public function confirmCode(string $email, string $code): SelfServiceSessionDTO
    {
        $email = $this->normalise($email);
        $failedCodesKey = 'self-service-failed-codes|'.hash('sha256', $email);

        if ($this->rateLimiter->tooManyAttempts($failedCodesKey, self::MAX_FAILED_CODES_PER_ADDRESS)) {
            throw new SelfServiceVerificationException(__('Too many incorrect codes. Wait 15 minutes and try again.'));
        }

        $verification = $this->pendingVerification($email);

        if ($verification === null || Carbon::parse($verification->getCodeExpiresAt())->isPast()) {
            throw new SelfServiceVerificationException(__('This code has expired. Request a new one.'));
        }

        $claimedAttempt = $this->verificationRepository->incrementWhere(
            [
                SelfServiceVerificationDomainObjectAbstract::ID => $verification->getId(),
                [SelfServiceVerificationDomainObjectAbstract::ATTEMPTS, '<', self::MAX_ATTEMPTS],
            ],
            SelfServiceVerificationDomainObjectAbstract::ATTEMPTS,
        ) === 1;

        if (! $claimedAttempt) {
            throw new SelfServiceVerificationException(__('Too many incorrect attempts. Request a new code.'));
        }

        if (! hash_equals((string) $verification->getCodeHash(), $this->hash($code, $email))) {
            $this->rateLimiter->hit($failedCodesKey, self::FAILED_CODES_LOCK_MINUTES * 60);

            throw new SelfServiceVerificationException(
                $verification->getAttempts() + 1 >= self::MAX_ATTEMPTS
                    ? __('Too many incorrect attempts. Request a new code.')
                    : __('That code is not correct. Check the email and try again.')
            );
        }

        $this->rateLimiter->clear($failedCodesKey);

        return $this->openSession($email, $verification->getId());
    }

    public function startSession(string $email): SelfServiceSessionDTO
    {
        return $this->openSession($this->normalise($email), null);
    }

    public function emailForSession(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        $verification = $this->verificationRepository->findFirstWhere([
            SelfServiceVerificationDomainObjectAbstract::SESSION_TOKEN_HASH => hash('sha256', $token),
        ]);

        if ($verification === null || Carbon::parse($verification->getSessionExpiresAt())->isPast()) {
            return null;
        }

        return $verification->getEmail();
    }

    private function openSession(string $email, ?int $verificationId): SelfServiceSessionDTO
    {
        $token = $this->tokenGeneratorService->generateToken(length: 48, prefix: 'ss_');
        $expiresAt = Carbon::now()->addMinutes(self::SESSION_TTL_MINUTES);

        $session = [
            SelfServiceVerificationDomainObjectAbstract::EMAIL => $email,
            SelfServiceVerificationDomainObjectAbstract::CODE_HASH => null,
            SelfServiceVerificationDomainObjectAbstract::CODE_EXPIRES_AT => null,
            SelfServiceVerificationDomainObjectAbstract::SESSION_TOKEN_HASH => hash('sha256', $token),
            SelfServiceVerificationDomainObjectAbstract::SESSION_EXPIRES_AT => $expiresAt->toDateTimeString(),
        ];

        if ($verificationId === null) {
            $this->verificationRepository->create($session);
        } else {
            $this->verificationRepository->updateWhere($session, [
                SelfServiceVerificationDomainObjectAbstract::ID => $verificationId,
            ]);
        }

        return new SelfServiceSessionDTO(
            token: $token,
            expires_at: $expiresAt->toIso8601String(),
            masked_email: EmailMasker::mask($email),
        );
    }

    private function pendingVerification(string $email): ?SelfServiceVerificationDomainObject
    {
        return $this->verificationRepository->findWhere(
            where: [
                SelfServiceVerificationDomainObjectAbstract::EMAIL => $email,
                [SelfServiceVerificationDomainObjectAbstract::SESSION_TOKEN_HASH, 'null', null],
            ],
            orderAndDirections: [
                new OrderAndDirection(SelfServiceVerificationDomainObjectAbstract::ID, OrderAndDirection::DIRECTION_DESC),
            ],
            limit: 1,
        )->first();
    }

    private function hash(string $code, string $email): string
    {
        return hash_hmac('sha256', $code.'|'.$email, (string) $this->config->get('app.key'));
    }

    private function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
