<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Enterprise\Sso\Services\Domain\SsoConnectionLinkService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\StartSsoDTO;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStartResult;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStateDTO;
use HiEvents\Services\Domain\Auth\Sso\OidcClient;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class StartSsoHandler
{
    private const RETURN_PATH_PATTERN = '#^/(?![/\\\\])[^\s\\\\]{0,500}$#';

    public function __construct(
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly SsoTicketService $ticketService,
        private readonly OidcClient $oidcClient,
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
        private readonly SsoConnectionLinkService $connectionLinkService,
    ) {}

    /**
     * @throws SsoException
     */
    public function handle(StartSsoDTO $dto): SsoStartResult
    {
        $provider = $this->providerRegistry->get($dto->providerKey, includeInactiveConnection: $dto->intent === SsoIntent::TEST);

        if ($provider->isAccountConnection() && ! in_array($dto->intent, SsoIntent::accountConnectionIntents(), true)) {
            throw new SsoException(SsoErrorCode::PROVIDER_NOT_CONFIGURED, __('This sign-in option is not available.'));
        }

        if ($dto->intent === SsoIntent::INVITE) {
            $this->assertInvitationIsPending($dto->invitationToken);
        }

        if ($dto->intent === SsoIntent::LINK && $provider->isAccountConnection()) {
            $this->connectionLinkService->assertCanLink($provider, (int) $dto->userId);
        }

        $binding = SsoTicketService::newSecret();
        $nonce = SsoTicketService::newSecret();
        $codeVerifier = SsoTicketService::newSecret();

        $state = $this->ticketService->issue(SsoTicketType::STATE, [
            'binding_hash' => SsoTicketService::hashBinding($binding),
            'state' => (new SsoStateDTO(
                providerKey: $provider->key,
                intent: $dto->intent,
                codeVerifier: $codeVerifier,
                nonce: $nonce,
                userId: $dto->userId,
                tokenId: $dto->tokenId,
                invitationToken: $dto->invitationToken,
                returnPath: $this->sanitiseReturnPath($dto->returnPath),
                timezone: $dto->timezone,
                marketingOptIn: $dto->marketingOptIn,
            ))->toArray(),
        ]);

        return new SsoStartResult(
            authorizationUrl: $this->oidcClient->authorizationUrl(
                provider: $provider,
                intent: $dto->intent,
                state: $state,
                nonce: $nonce,
                codeVerifier: $codeVerifier,
                switchAccount: $dto->switchAccount,
                loginHint: $dto->loginHint,
                silent: $dto->silent && $dto->intent === SsoIntent::LOGIN,
            ),
            binding: $binding,
        );
    }

    /**
     * @throws SsoException
     */
    private function assertInvitationIsPending(?string $invitationToken): void
    {
        try {
            $this->invitationAcceptanceService->findPending((string) $invitationToken);
        } catch (DecryptionFailedException|EncryptedPayloadExpiredException|ResourceConflictException|ResourceNotFoundException) {
            throw new SsoException(SsoErrorCode::INVITE_INVALID, __('This invitation is no longer valid.'));
        }
    }

    private function sanitiseReturnPath(?string $returnPath): ?string
    {
        if ($returnPath === null || preg_match(self::RETURN_PATH_PATTERN, $returnPath) !== 1) {
            return null;
        }

        return $returnPath;
    }
}
