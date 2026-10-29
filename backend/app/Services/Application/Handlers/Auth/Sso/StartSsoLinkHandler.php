<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\StartSsoDTO;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStartResult;

class StartSsoLinkHandler
{
    public function __construct(
        private readonly IdentityConfirmationService $identityConfirmationService,
        private readonly StartSsoHandler $startSsoHandler,
    ) {}

    /**
     * @throws IdentityConfirmationFailedException
     * @throws SsoException
     */
    public function handle(
        UserDomainObject $user,
        string $providerKey,
        string $tokenId,
        ?string $password,
        ?string $reauthProof,
        ?string $returnPath,
    ): SsoStartResult {
        $this->identityConfirmationService->confirm($user, $password, $reauthProof, $tokenId);

        return $this->startSsoHandler->handle(new StartSsoDTO(
            providerKey: $providerKey,
            intent: SsoIntent::LINK,
            returnPath: $returnPath,
            userId: $user->getId(),
            tokenId: $tokenId,
        ));
    }
}
