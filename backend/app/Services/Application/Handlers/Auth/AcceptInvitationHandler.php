<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\Exceptions\InvitationSignInRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Application\Handlers\Auth\DTO\AcceptInvitationDTO;
use HiEvents\Services\Domain\Auth\Invitation\InvitationAcceptanceService;
use HiEvents\Services\Domain\Auth\PasswordLoginPolicy;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class AcceptInvitationHandler
{
    public function __construct(
        private readonly InvitationAcceptanceService $invitationAcceptanceService,
        private readonly PasswordLoginPolicy $passwordLoginPolicy,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     * @throws ResourceConflictException
     * @throws InvitationSignInRequiredException
     * @throws Throwable
     */
    public function handle(AcceptInvitationDTO $invitationData): void
    {
        $this->databaseManager->transaction(function () use ($invitationData) {
            $invitation = $this->invitationAcceptanceService->findPending($invitationData->invitation_token);

            if ($invitation->requiresSignIn) {
                throw new InvitationSignInRequiredException(__('Sign in to accept this invitation.'));
            }

            if ($this->passwordLoginPolicy->isDisabled()) {
                throw new ResourceConflictException(__('Password sign-in is turned off. Use one of the sign-in options instead.'));
            }

            $this->invitationAcceptanceService->setUpNewUser(
                invitation: $invitation,
                firstName: $invitationData->first_name,
                lastName: $invitationData->last_name,
                password: $invitationData->password,
                timezone: $invitationData->timezone,
                marketingOptIn: $invitationData->marketing_opt_in,
            );
        });
    }
}
