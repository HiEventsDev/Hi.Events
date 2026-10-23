<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\SsoRequiredException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\InvitationPendingException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Collection;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class LoginService
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly TwoFactorChallengeService $twoFactorChallengeService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly UserIdentityService $userIdentityService,
        private readonly PasswordLoginPolicy $passwordLoginPolicy,
        private readonly SsoEnforcementService $ssoEnforcementService,
        private readonly AccountSsoConnectionRepositoryInterface $ssoConnectionRepository,
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

        if (! $this->passwordLoginPolicy->isAllowedFor($userModel->id)) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        return $this->beginLogin(
            user: UserDomainObject::hydrateFromModel($userModel),
            requestedAccountId: $requestedAccountId,
            trustedDeviceToken: $trustedDeviceToken,
            context: AuthContext::password(),
        );
    }

    /**
     * @throws UnauthorizedException
     * @throws SsoRequiredException
     */
    public function beginLogin(
        UserDomainObject $user,
        ?int $requestedAccountId,
        ?string $trustedDeviceToken,
        AuthContext $context,
    ): LoginResponse {
        if ($this->twoFactorAuthenticationService->isEnabled($user)
            && ($this->ssoEnforcementService->requiresFreshSecondFactor($user->getId(), $context)
                || ! $this->trustedDeviceService->isTrusted($user->getId(), $trustedDeviceToken))) {
            $accountId = $this->resolveAccountToSignInTo($user, $requestedAccountId);

            if ($accountId !== null) {
                $this->ssoEnforcementService->assertMemberSignInAllowed($user, $accountId, $context);
            }

            return new LoginResponse(
                accounts: collect(),
                token: null,
                user: $user,
                twoFactorChallengeToken: $this->twoFactorChallengeService->create($user->getId(), $context),
            );
        }

        return $this->completeLogin($user, $requestedAccountId, $context);
    }

    /**
     * @throws UnauthorizedException
     * @throws SsoRequiredException
     */
    public function completeLogin(UserDomainObject $user, ?int $requestedAccountId, AuthContext $context): LoginResponse
    {
        $requestedAccountId = $this->accountRequiredByContext($requestedAccountId, $context);
        $userAccounts = $this->findActiveUserAccounts($user);

        $accounts = $userAccounts->map(fn ($accountUser) => $accountUser->getAccount());

        $accountId = $this->getAccountId($user, $accounts, $requestedAccountId);

        if ($accountId === null) {
            return new LoginResponse(
                accounts: $accounts,
                token: null,
                user: $user,
            );
        }

        $this->ssoEnforcementService->assertSignInAllowed($user, $accountId, $context);

        if ($context->pendingIdentity !== null) {
            $this->userIdentityService->attachForSignIn($user, $context->pendingIdentity);
        }

        $token = $this->issueToken($user->getId(), $accountId, $this->getUserRole($accountId, $userAccounts), $context);

        $this->accountUserRepository->updateWhere(
            attributes: ['last_login_at' => now()],
            where: [
                'user_id' => $user->getId(),
                'account_id' => $accountId,
            ],
        );

        return new LoginResponse(
            accounts: $accounts,
            token: $token,
            user: $user,
            accountId: $accountId,
        );
    }

    /**
     * @throws UnauthorizedException
     */
    private function accountRequiredByContext(?int $requestedAccountId, AuthContext $context): ?int
    {
        if ($context->accountId === null) {
            return $requestedAccountId;
        }

        if ($requestedAccountId !== null && $requestedAccountId !== $context->accountId) {
            throw new UnauthorizedException(__('Account not found'));
        }

        return $context->accountId;
    }

    /**
     * @throws UnauthorizedException
     */
    private function resolveAccountToSignInTo(UserDomainObject $user, ?int $requestedAccountId): ?int
    {
        return $this->getAccountId(
            $user,
            $this->findActiveUserAccounts($user)->map(fn ($accountUser) => $accountUser->getAccount()),
            $requestedAccountId,
        );
    }

    private function findActiveUserAccounts(UserDomainObject $user): Collection
    {
        return $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: AccountDomainObject::class, name: 'account'))
            ->findWhere([
                'user_id' => $user->getId(),
                'status' => UserStatus::ACTIVE->name,
            ]);
    }

    /**
     * @throws UnauthorizedException
     */
    private function getAccountId(UserDomainObject $user, Collection $accounts, ?int $requestedAccountId): ?int
    {
        if ($accounts->isEmpty()) {
            $this->throwNoActiveAccount($user);
        }

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

    /**
     * @throws UnauthorizedException
     */
    private function throwNoActiveAccount(UserDomainObject $user): never
    {
        $hasPendingInvitation = $this->accountUserRepository->findFirstWhere([
            'user_id' => $user->getId(),
            'status' => UserStatus::INVITED->name,
        ]) !== null;

        if ($hasPendingInvitation) {
            throw new InvitationPendingException(
                __('You have been invited to join a team. Open the invitation email to finish joining.'),
            );
        }

        throw new UnauthorizedException(__('User account is not active'));
    }

    private function issueToken(int $userId, int $accountId, Role $userRole, AuthContext $context): string
    {
        $claims = [
            'account_id' => $accountId,
            'role' => $userRole->value,
            'auth_method' => $context->method->value,
        ];

        $connection = $context->ssoConnectionId === null
            ? null
            : $this->ssoConnectionRepository->findFirstWhere(['id' => $context->ssoConnectionId, 'account_id' => $accountId]);

        $guard = $this->guard();
        $defaultTtl = $guard->factory()->getTTL();

        if ($connection !== null) {
            $claims['sso_connection_id'] = $connection->getId();
            $guard->setTTL($connection->getSessionTtlMinutes());
        }

        try {
            $token = $guard->claims($claims)->tokenById($userId);
        } finally {
            $guard->setTTL($defaultTtl);
        }

        if ($token === null) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        return $token;
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
