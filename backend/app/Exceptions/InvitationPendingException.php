<?php

namespace HiEvents\Exceptions;

class InvitationPendingException extends CodedAccessException
{
    public const ERROR_CODE = 'INVITATION_PENDING';
}
