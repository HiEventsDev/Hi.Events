<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoExchangeStatus;
use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Enterprise\Sso\Exceptions\SsoRequiredException;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoConnectionService;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;
use HiEvents\Enterprise\Sso\Services\Domain\EnterpriseSsoResolver;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\ExchangeSsoCodeDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\SsoExchangeResult;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Domain\Auth\Invitation\PendingInvitation;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStateDTO;
use HiEvents\Services\Domain\Auth\Sso\OidcClient;
use HiEvents\Services\Domain\Auth\Sso\SsoIdentityResolver;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class ExchangeSsoCodeHandler
{
    private const REAUTH_MAX_AGE_SECONDS = 300;

    public function __construct(
        private readonly SsoTicketService $ticketService,
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly OidcClient $oidcClient,
        private readonly SsoIdentityResolver $identityResolver,
        private readonly UserIdentityService $userIdentityService,
        private readonly LoginService $loginService,
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly Config $config,
        private readonly EnterpriseSsoResolver $enterpriseResolver,
        private readonly AccountSsoConnectionService $connectionService,
        private readonly AccountSsoDomainService $domainService,
        private readonly SsoEnforcementService $enforcementService,
    ) {}

    /**
     * @param  SsoIntent[]  $allowedIntents
     *
     * @throws SsoException
     * @throws UnauthorizedException
     * @throws Throwable
     */
    public function handle(ExchangeSsoCodeDTO $dto, array $allowedIntents): SsoExchangeResult
    {
        $state = SsoStateDTO::from($this->ticketService->consume(SsoTicketType::STATE, $dto->state, $dto->binding)['state']);

        if (! in_array($state->intent, $allowedIntents, true)) {
            throw new SsoException(SsoErrorCode::STATE_INVALID, __('This sign-in link has expired. Please try again.'));
        }

        $provider = $this->providerRegistry->get($state->providerKey, includeInactiveConnection: $state->intent === SsoIntent::TEST);

        $intentAllowed = $provider->isAccountConnection()
            ? in_array($state->intent, SsoIntent::accountConnectionIntents(), true)
            : $state->intent !== SsoIntent::TEST;

        if (! $intentAllowed) {
            throw new SsoException(SsoErrorCode::STATE_INVALID, __('This sign-in link has expired. Please try again.'));
        }

        $claims = $this->oidcClient->exchange($provider, $dto->code, $state->codeVerifier, $state->nonce);
        $bindingHash = SsoTicketService::hashBinding($dto->binding);

        return match ($state->intent) {
            SsoIntent::LOGIN, SsoIntent::SIGNUP => $this->signIn($provider, $state, $claims, $bindingHash, $dto),
            SsoIntent::INVITE => $this->acceptInvitation($provider, $state, $claims, $bindingHash, $dto),
            SsoIntent::LINK => $this->link($provider, $state, $claims, $dto),
            SsoIntent::REAUTH => $this->reauthenticate($provider, $state, $claims, $dto),
            SsoIntent::TEST => $this->test($provider, $state, $claims, $dto),
        };
    }

    /**
     * @throws SsoException
     * @throws UnauthorizedException
     */
    private function signIn(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, string $bindingHash, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        if ($provider->isAccountConnection()) {
            return $this->signInWithAccountConnection($provider, $state, $claims, $bindingHash, $dto);
        }

        $user = $this->identityResolver->resolve($claims);

        if ($user === null) {
            return $this->signupRequired($provider, $state, $claims, $bindingHash);
        }

        $loginResponse = $this->loginService->beginLogin(
            user: $user,
            requestedAccountId: null,
            trustedDeviceToken: $dto->trustedDeviceToken,
            context: $this->context($claims, $bindingHash),
        );

        return $this->loginResult($provider, $state, $claims, $bindingHash, $loginResponse);
    }

    /**
     * @throws SsoException
     * @throws UnauthorizedException
     * @throws Throwable
     */
    private function signInWithAccountConnection(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, string $bindingHash, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        $loginResponse = $this->databaseManager->transaction(fn () => $this->loginService->beginLogin(
            user: $this->enterpriseResolver->resolve($provider, $claims),
            requestedAccountId: $provider->accountId,
            trustedDeviceToken: $dto->trustedDeviceToken,
            context: $this->context($claims, $bindingHash),
        ));

        return $this->loginResult($provider, $state, $claims, $bindingHash, $loginResponse);
    }

    /**
     * @throws SsoException
     */
    private function test(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        $this->assertSameSession($state, $dto);

        $this->connectionService->markTested((int) $provider->connectionId);

        return new SsoExchangeResult(
            status: SsoExchangeStatus::TESTED,
            providerName: $provider->name,
            returnPath: $state->returnPath,
            testResult: [
                'email' => $claims->email,
                'email_verified' => $claims->emailTrusted,
                'email_domain_verified' => $this->domainService->isEmailOnUsableDomain((int) $provider->accountId, $claims->email),
                'first_name' => $claims->firstName,
                'last_name' => $claims->lastName,
                'groups' => $claims->groups,
            ],
        );
    }

    /**
     * @throws SsoException
     * @throws SsoRequiredException
     */
    private function signupRequired(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, string $bindingHash): SsoExchangeResult
    {
        if ($this->config->get('app.disable_registration')) {
            throw new SsoException(SsoErrorCode::REGISTRATION_DISABLED, __('No account was found. Ask your administrator to invite you.'));
        }

        if ($claims->email === null) {
            throw new SsoException(
                SsoErrorCode::EMAIL_REQUIRED,
                __(':provider did not share your email address. Allow access to your email and try again.', ['provider' => $provider->name]),
            );
        }

        $this->enforcementService->assertSignupAllowed($claims->email);

        return new SsoExchangeResult(
            status: SsoExchangeStatus::SIGNUP_REQUIRED,
            providerName: $provider->name,
            signupTicket: $this->ticketService->issue(SsoTicketType::SIGNUP, [
                'claims' => $claims->toArray(),
                'binding_hash' => $bindingHash,
            ]),
            email: $claims->email,
            emailVerified: $claims->emailTrusted,
            firstName: $claims->firstName,
            lastName: $claims->lastName,
            returnPath: $state->returnPath,
        );
    }

    /**
     * @throws SsoException
     * @throws UnauthorizedException
     * @throws Throwable
     */
    private function acceptInvitation(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, string $bindingHash, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        $loginResponse = $this->databaseManager->transaction(function () use ($provider, $state, $claims, $bindingHash, $dto) {
            $invitation = $this->findInvitation((string) $state->invitationToken);
            $invitedUser = $invitation->user;
            $identity = $this->userIdentityService->find($claims);

            if ($identity !== null && $identity->getUserId() !== $invitedUser->getId()) {
                throw new SsoException(SsoErrorCode::IDENTITY_IN_USE, __('This account is already connected to another user.'));
            }

            if ($identity === null) {
                $this->assertCanAcceptAsNewUser($provider, $invitation, $claims);
                $this->invitationAcceptanceService->setUpNewUser(
                    invitation: $invitation,
                    firstName: $invitedUser->getFirstName() ?: ($claims->firstName ?? $invitedUser->getEmail()),
                    lastName: $invitedUser->getLastName() ?: $claims->lastName,
                    password: null,
                    timezone: $state->timezone ?? $invitedUser->getTimezone(),
                    marketingOptIn: $state->marketingOptIn,
                );
            } else {
                $this->invitationAcceptanceService->activate($invitation);
            }

            return $this->loginService->beginLogin(
                user: $invitedUser,
                requestedAccountId: $invitation->accountId,
                trustedDeviceToken: $dto->trustedDeviceToken,
                context: $this->context($claims, $bindingHash),
            );
        });

        return $this->loginResult($provider, $state, $claims, $bindingHash, $loginResponse);
    }

    /**
     * @throws SsoException
     */
    private function findInvitation(string $invitationToken): PendingInvitation
    {
        try {
            return $this->invitationAcceptanceService->findPending($invitationToken);
        } catch (DecryptionFailedException|EncryptedPayloadExpiredException|ResourceConflictException|ResourceNotFoundException) {
            throw new SsoException(SsoErrorCode::INVITE_INVALID, __('This invitation is no longer valid.'));
        }
    }

    /**
     * @throws SsoException
     */
    private function assertCanAcceptAsNewUser(SsoProviderConfig $provider, PendingInvitation $invitation, SsoIdentityClaims $claims): void
    {
        if ($invitation->requiresSignIn) {
            throw new SsoException(SsoErrorCode::INVITE_SIGN_IN_REQUIRED, __('Sign in to your existing account to accept this invitation.'));
        }

        $invitedEmail = strtolower($invitation->user->getEmail());

        if ($claims->email === null) {
            throw new SsoException(
                SsoErrorCode::EMAIL_REQUIRED,
                __(':provider did not share your email address. Allow access to your email and try again.', ['provider' => $provider->name]),
            );
        }

        if ($claims->email !== $invitedEmail) {
            throw new SsoException(
                SsoErrorCode::INVITE_EMAIL_MISMATCH,
                __('You signed in as :signedInAs, but this invitation is for :invitedEmail.', [
                    'signedInAs' => $claims->email,
                    'invitedEmail' => $invitedEmail,
                ]),
                ['signed_in_as' => $claims->email, 'invited_email' => $invitedEmail],
            );
        }
    }

    /**
     * @throws SsoException
     */
    private function link(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        $this->assertSameSession($state, $dto);

        $this->userIdentityService->attach($this->userRepository->findById((int) $state->userId), $claims);

        return new SsoExchangeResult(
            status: SsoExchangeStatus::LINKED,
            providerName: $provider->name,
            returnPath: $state->returnPath,
        );
    }

    /**
     * @throws SsoException
     */
    private function reauthenticate(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, ExchangeSsoCodeDTO $dto): SsoExchangeResult
    {
        $this->assertSameSession($state, $dto);

        $identity = $this->userIdentityService->find($claims);
        $isFresh = $claims->authTime === null || (now()->getTimestamp() - $claims->authTime) <= self::REAUTH_MAX_AGE_SECONDS;

        $connectionCannotConfirm = $provider->isAccountConnection()
            && $this->enterpriseResolver->hasMembershipsOutside((int) $state->userId, (int) $provider->accountId);

        if ($identity === null || $identity->getUserId() !== $state->userId || ! $isFresh || $connectionCannotConfirm) {
            throw new SsoException(SsoErrorCode::REAUTH_FAILED, __('We couldn\'t confirm it\'s you. Sign in with an account you have already connected.'));
        }

        $this->userIdentityService->touch($identity, $claims);

        return new SsoExchangeResult(
            status: SsoExchangeStatus::REAUTHENTICATED,
            providerName: $provider->name,
            reauthProof: $this->ticketService->issue(SsoTicketType::REAUTH, [
                'user_id' => $state->userId,
                'token_id' => $state->tokenId,
            ]),
            returnPath: $state->returnPath,
        );
    }

    private function loginResult(SsoProviderConfig $provider, SsoStateDTO $state, SsoIdentityClaims $claims, string $bindingHash, LoginResponse $loginResponse): SsoExchangeResult
    {
        if ($loginResponse->twoFactorChallengeToken !== null) {
            return new SsoExchangeResult(
                status: SsoExchangeStatus::TWO_FACTOR_REQUIRED,
                providerName: $provider->name,
                loginResponse: $loginResponse,
                returnPath: $state->returnPath,
            );
        }

        if ($loginResponse->token !== null) {
            return new SsoExchangeResult(
                status: SsoExchangeStatus::AUTHENTICATED,
                providerName: $provider->name,
                loginResponse: $loginResponse,
                returnPath: $state->returnPath,
            );
        }

        return new SsoExchangeResult(
            status: SsoExchangeStatus::CHOOSE_ACCOUNT,
            providerName: $provider->name,
            loginResponse: $loginResponse,
            loginTicket: $this->ticketService->issue(SsoTicketType::LOGIN, [
                'user_id' => $loginResponse->user->getId(),
                'context' => $this->context($claims, $bindingHash)->toArray(),
                'binding_hash' => $bindingHash,
            ]),
            returnPath: $state->returnPath,
        );
    }

    private function context(SsoIdentityClaims $claims, string $bindingHash): AuthContext
    {
        return new AuthContext(
            method: AuthMethod::SSO,
            pendingIdentity: $claims,
            bindingHash: $bindingHash,
            ssoConnectionId: $claims->connectionId,
            accountId: $claims->connectionId === null ? null : $this->connectionService->accountIdFor($claims->connectionId),
        );
    }

    /**
     * @throws SsoException
     */
    private function assertSameSession(SsoStateDTO $state, ExchangeSsoCodeDTO $dto): void
    {
        $sameUser = $state->userId !== null && $state->userId === $dto->authenticatedUserId;
        $sameToken = $state->tokenId !== null && $dto->tokenId !== null && hash_equals($state->tokenId, $dto->tokenId);

        if (! $sameUser || ! $sameToken) {
            throw new SsoException(SsoErrorCode::STATE_INVALID, __('This sign-in link has expired. Please try again.'));
        }
    }
}
