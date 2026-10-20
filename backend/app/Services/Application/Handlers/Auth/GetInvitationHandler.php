<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Application\Handlers\Auth\DTO\InvitationDetailsDTO;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;

class GetInvitationHandler
{
    public function __construct(
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
        private readonly SsoEnforcementService $ssoEnforcementService,
    ) {}

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     * @throws ResourceConflictException
     */
    public function handle(string $invitationToken): InvitationDetailsDTO
    {
        $invitation = $this->invitationAcceptanceService->findPending($invitationToken);

        return new InvitationDetailsDTO(
            invitation: $invitation,
            ssoRequired: $this->ssoEnforcementService->requiresSsoToJoin(
                $invitation->user->getId(),
                $invitation->user->getEmail(),
                $invitation->accountId,
            ),
        );
    }
}
