<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\MfaAuditAction;
use HiEvents\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Psr\Log\LoggerInterface;

readonly class MfaService
{
    private Google2FA $google2fa;

    public function __construct(
        private MfaAuditService $auditService,
        private LoggerInterface $logger,
    )
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a new TOTP secret for enrollment.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Generate QR code provisioning URL for authenticator apps.
     */
    public function getQrCodeUrl(string $email, string $secret): string
    {
        $appName = config('app.name', 'Hi.Events');

        return $this->google2fa->getQRCodeUrl(
            $appName,
            $email,
            $secret,
        );
    }

    /**
     * Verify a TOTP code against the secret (with clock drift tolerance).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        // Allow for 1 window of clock drift (30 seconds before/after)
        return (bool)$this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Enable MFA for a user. Encrypts the secret at rest.
     */
    public function enableMfa(User $user, string $secret, string $code, Request $request): array
    {
        if (!$this->verifyCode($secret, $code)) {
            $this->auditService->log(
                userId: $user->id,
                action: MfaAuditAction::MFA_CHALLENGE_FAILED,
                request: $request,
                metadata: ['reason' => 'invalid_code_during_setup'],
            );
            return ['success' => false, 'error' => __('Invalid verification code')];
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_recovery_codes' => Crypt::encryptString(json_encode(
                $recoveryCodes->map(fn(string $code) => hash('sha256', $code))->toArray()
            )),
            'mfa_confirmed_at' => now(),
        ]);

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::MFA_ENABLED,
            request: $request,
        );

        return [
            'success' => true,
            'recovery_codes' => $recoveryCodes->toArray(),
        ];
    }

    /**
     * Disable MFA for a user.
     */
    public function disableMfa(User $user, Request $request): void
    {
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
        ]);

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::MFA_DISABLED,
            request: $request,
        );
    }

    /**
     * Verify a TOTP code for MFA challenge during login.
     */
    public function verifyMfaChallenge(User $user, string $code, Request $request): bool
    {
        if (!$user->mfa_enabled || !$user->mfa_secret) {
            return false;
        }

        $secret = Crypt::decryptString($user->mfa_secret);
        $valid = $this->verifyCode($secret, $code);

        $this->auditService->log(
            userId: $user->id,
            action: $valid ? MfaAuditAction::MFA_CHALLENGE_SUCCESS : MfaAuditAction::MFA_CHALLENGE_FAILED,
            request: $request,
        );

        return $valid;
    }

    /**
     * Verify a recovery code (one-time use).
     */
    public function verifyRecoveryCode(User $user, string $recoveryCode, Request $request): bool
    {
        if (!$user->mfa_recovery_codes) {
            return false;
        }

        $hashedCodes = json_decode(Crypt::decryptString($user->mfa_recovery_codes), true);
        $hashedInput = hash('sha256', $recoveryCode);

        $index = array_search($hashedInput, $hashedCodes, true);
        if ($index === false) {
            $this->auditService->log(
                userId: $user->id,
                action: MfaAuditAction::MFA_CHALLENGE_FAILED,
                request: $request,
                metadata: ['reason' => 'invalid_recovery_code'],
            );
            return false;
        }

        // Remove used recovery code
        unset($hashedCodes[$index]);
        $user->update([
            'mfa_recovery_codes' => Crypt::encryptString(json_encode(array_values($hashedCodes))),
        ]);

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::MFA_RECOVERY_USED,
            request: $request,
            metadata: ['remaining_codes' => count($hashedCodes)],
        );

        return true;
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(User $user, Request $request): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'mfa_recovery_codes' => Crypt::encryptString(json_encode(
                $recoveryCodes->map(fn(string $code) => hash('sha256', $code))->toArray()
            )),
        ]);

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::MFA_ENABLED,
            request: $request,
            metadata: ['action' => 'recovery_codes_regenerated'],
        );

        return $recoveryCodes->toArray();
    }

    /**
     * Generate 10 recovery codes (8 chars each, alphanumeric).
     */
    private function generateRecoveryCodes(): Collection
    {
        return collect(range(1, 10))->map(fn() => Str::upper(Str::random(4) . '-' . Str::random(4)));
    }
}
