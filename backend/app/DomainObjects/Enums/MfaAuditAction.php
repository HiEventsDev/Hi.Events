<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

enum MfaAuditAction: string
{
    case MFA_ENABLED = 'mfa_enabled';
    case MFA_DISABLED = 'mfa_disabled';
    case MFA_CHALLENGE_SUCCESS = 'mfa_challenge_success';
    case MFA_CHALLENGE_FAILED = 'mfa_challenge_failed';
    case MFA_RECOVERY_USED = 'mfa_recovery_used';
    case PASSKEY_REGISTERED = 'passkey_registered';
    case PASSKEY_REMOVED = 'passkey_removed';
    case PASSKEY_LOGIN = 'passkey_login';
    case OAUTH_LOGIN = 'oauth_login';
    case OAUTH_LINKED = 'oauth_linked';
}
