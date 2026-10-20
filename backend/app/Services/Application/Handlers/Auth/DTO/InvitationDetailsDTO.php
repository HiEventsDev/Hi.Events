<?php

namespace HiEvents\Services\Application\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\Auth\Invitation\PendingInvitation;

class InvitationDetailsDTO extends BaseDataObject
{
    public function __construct(
        public readonly PendingInvitation $invitation,
        public readonly bool $ssoRequired,
    ) {}
}
