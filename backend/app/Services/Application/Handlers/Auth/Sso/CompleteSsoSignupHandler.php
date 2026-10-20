<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\CompleteSsoSignupDTO;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\SsoIdentityResolver;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CompleteSsoSignupHandler
{
    private const MAX_CODE_ATTEMPTS = 5;

    private const LOCK_SECONDS = 30;

    public function __construct(
        private readonly SsoTicketService $ticketService,
        private readonly SsoIdentityResolver $identityResolver,
        private readonly UserIdentityService $userIdentityService,
        private readonly CreateAccountHandler $createAccountHandler,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginService $loginService,
        private readonly DatabaseManager $databaseManager,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws SsoException
     * @throws UnauthorizedException
     * @throws Throwable
     */
    public function handle(CompleteSsoSignupDTO $dto): LoginResponse
    {
        $payload = $this->ticketService->find(SsoTicketType::SIGNUP, $dto->signupTicket, $dto->binding);
        $claims = SsoIdentityClaims::from($payload['claims']);
        $lockKey = 'sso_signup_lock:'.hash('sha256', $claims->issuer.'|'.$claims->subject);

        if (! $this->cache->add($lockKey, true, self::LOCK_SECONDS)) {
            throw new SsoException(SsoErrorCode::TICKET_INVALID, __('Your account is already being created. Please wait a moment.'));
        }

        try {
            if (! $claims->emailTrusted) {
                $this->verifyEmailCode($dto, $payload);
            }

            $this->assertStillAvailable($claims);

            $user = $this->databaseManager->transaction(function () use ($dto, $claims) {
                $this->createAccountHandler->handle(CreateAccountDTO::fromArray([
                    ...$dto->attribution,
                    'email' => (string) $claims->email,
                    'password' => null,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName,
                    'timezone' => $dto->timezone,
                    'currency_code' => $dto->currencyCode,
                    'locale' => $dto->locale ?? config('app.locale'),
                    'invite_token' => $dto->inviteToken,
                    'marketing_opt_in' => $dto->marketingOptIn,
                    'allow_existing_user' => false,
                    'email_verified' => true,
                ]));

                $user = $this->userRepository->findFirstWhere(['email' => strtolower((string) $claims->email)]);
                $this->userIdentityService->attach($user, $claims, notify: false);

                return $user;
            });
        } catch (EmailAlreadyExists) {
            throw $this->emailTaken();
        } finally {
            $this->cache->forget($lockKey);
        }

        $this->ticketService->consume(SsoTicketType::SIGNUP, $dto->signupTicket, $dto->binding);

        return $this->loginService->completeLogin($user, null, new AuthContext(method: AuthMethod::SSO));
    }

    /**
     * @throws SsoException
     */
    private function verifyEmailCode(CompleteSsoSignupDTO $dto, array $payload): void
    {
        $codeHash = $payload['email_code_hash'] ?? null;
        $attempts = (int) ($payload['email_code_attempts'] ?? 0) + 1;

        if ($codeHash === null || $dto->emailCode === null || ! hash_equals($codeHash, hash('sha256', trim($dto->emailCode)))) {
            if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                $this->ticketService->forget(SsoTicketType::SIGNUP, $dto->signupTicket);

                throw new SsoException(SsoErrorCode::TICKET_INVALID, __('Too many incorrect codes. Please start again.'));
            }

            $this->ticketService->update(SsoTicketType::SIGNUP, $dto->signupTicket, [
                ...$payload,
                'email_code_attempts' => $attempts,
            ]);

            throw new SsoException(SsoErrorCode::EMAIL_CODE_INVALID, __('That code is not valid. Check your email and try again.'));
        }
    }

    /**
     * @throws SsoException
     */
    private function assertStillAvailable(SsoIdentityClaims $claims): void
    {
        if ($this->userIdentityService->find($claims) !== null || $this->identityResolver->emailIsTaken((string) $claims->email)) {
            throw $this->emailTaken();
        }
    }

    private function emailTaken(): SsoException
    {
        return new SsoException(
            SsoErrorCode::EMAIL_TAKEN,
            __('An account with this email already exists. Sign in instead.'),
        );
    }
}
