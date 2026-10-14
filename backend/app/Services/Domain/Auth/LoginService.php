<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Collection;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Psr\Log\LoggerInterface;

class LoginService
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly LoggerInterface $logger,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly TwoFactorChallengeService $twoFactorChallengeService,
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function authenticate(
        string $email,
        string $password,
        ?int $requestedAccountId,
        ?string $trustedDeviceToken = null,
    ): LoginResponse {
        $credentials = [
            'email' => strtolower($email),
            'password' => $password,
        ];

        if (! $this->guard()->validate($credentials)) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        /** @var User $userModel */
        $userModel = $this->guard()->getLastAttempted();
        $user = UserDomainObject::hydrateFromModel($userModel);

        if ($this->twoFactorAuthenticationService->isEnabled($user)
            && ! $this->trustedDeviceService->isTrusted($user->getId(), $trustedDeviceToken)) {
            $this->assertCanSignInTo($user, $requestedAccountId);

            return new LoginResponse(
                accounts: collect(),
                token: null,
                user: $user,
                twoFactorChallengeToken: $this->twoFactorChallengeService->create($user->getId()),
            );
        }

        return $this->completeLogin($user, $requestedAccountId);
    }

    /**
     * @throws UnauthorizedException
     */
    public function completeLogin(UserDomainObject $user, ?int $requestedAccountId): LoginResponse
    {
        $userAccounts = $this->findUserAccounts($user);

        $accounts = $userAccounts->map(fn ($accountUser) => $accountUser->getAccount());

        $accountId = $this->getAccountId($accounts, $requestedAccountId);

        if ($accountId === null) {
            return new LoginResponse(
                accounts: $accounts,
                token: null,
                user: $user,
            );
        }

        $this->validateUserStatus($accountId, $userAccounts);

        return new LoginResponse(
            accounts: $accounts,
            token: $this->issueToken($user->getId(), $accountId, $this->getUserRole($accountId, $userAccounts)),
            user: $user,
            accountId: $accountId,
        );
    }

    private function assertCanSignInTo(UserDomainObject $user, ?int $requestedAccountId): void
    {
        $userAccounts = $this->findUserAccounts($user);
        $accountId = $this->getAccountId($userAccounts->map(fn ($accountUser) => $accountUser->getAccount()), $requestedAccountId);

        if ($accountId !== null) {
            $this->validateUserStatus($accountId, $userAccounts);
        }
    }

    private function findUserAccounts(UserDomainObject $user): Collection
    {
        return $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: AccountDomainObject::class, name: 'account'))
            ->findWhere([
                'user_id' => $user->getId(),
            ]);
    }

    private function getAccountId(Collection $accounts, ?int $requestedAccountId): ?int
    {
        if ($accounts->count() === 1) {
            return $accounts->first()->getId();
        }

        if ($requestedAccountId) {
            $verifiedAccount = $accounts->firstWhere(fn (AccountDomainObject $account) => $account->getId() === $requestedAccountId);

            if ($verifiedAccount === null) {
                throw new UnauthorizedException(__('Account not found'));
            }

            return $verifiedAccount->getId();
        }

        return null;
    }

    private function issueToken(int $userId, int $accountId, Role $userRole): string
    {
        $token = $this->guard()
            ->claims([
                'account_id' => $accountId,
                'role' => $userRole->value,
            ])
            ->tokenById($userId);

        if ($token === null) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        return $token;
    }

    private function validateUserStatus(int $accountId, Collection $userAccounts): void
    {
        /** @var AccountUserDomainObject $currentAccount */
        $currentAccount = $userAccounts
            ->first(fn (AccountUserDomainObject $userAccount) => $userAccount->getAccountId() === $accountId);

        if ($currentAccount->getStatus() !== UserStatus::ACTIVE->name) {
            $this->logger->info(__('Attempt to log in to a non-active account'), $currentAccount->toArray());

            throw new UnauthorizedException(__('User account is not active'));
        }
    }

    private function getUserRole(int $accountId, Collection $userAccounts): Role
    {
        /** @var AccountUserDomainObject $currentAccount */
        $currentAccount = $userAccounts
            ->first(fn (AccountUserDomainObject $userAccount) => $userAccount->getAccountId() === $accountId);

        return Role::from($currentAccount->getRole());
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return $this->authManager->guard('api');
    }
}
