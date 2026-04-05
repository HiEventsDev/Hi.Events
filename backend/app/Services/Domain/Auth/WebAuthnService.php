<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\MfaAuditAction;
use HiEvents\Models\User;
use HiEvents\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

readonly class WebAuthnService
{
    public function __construct(
        private MfaAuditService $auditService,
        private LoggerInterface $logger,
    )
    {
    }

    /**
     * Generate registration options for a new passkey.
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

        // Cache challenge for verification (5 minute TTL)
        $cacheKey = "webauthn_reg_{$user->id}";
        Cache::put($cacheKey, $challengeB64, 300);

        $existingCredentials = $user->webAuthnCredentials->map(fn(WebAuthnCredential $cred) => [
            'type' => 'public-key',
            'id' => $cred->credential_id,
        ])->toArray();

        return [
            'challenge' => $challengeB64,
            'rp' => [
                'name' => config('app.name', 'Hi.Events'),
                'id' => parse_url(config('app.frontend_url', config('app.url')), PHP_URL_HOST),
            ],
            'user' => [
                'id' => rtrim(strtr(base64_encode((string)$user->id), '+/', '-_'), '='),
                'name' => $user->email,
                'displayName' => $user->first_name . ' ' . ($user->last_name ?? ''),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
                'residentKey' => 'preferred',
                'requireResidentKey' => false,
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $existingCredentials,
        ];
    }

    /**
     * Verify and register a new passkey credential.
     */
    public function verifyRegistration(User $user, array $credential, string $name, Request $request): WebAuthnCredential
    {
        $cacheKey = "webauthn_reg_{$user->id}";
        $expectedChallenge = Cache::get($cacheKey);

        if (!$expectedChallenge) {
            throw new \RuntimeException(__('Registration challenge expired. Please try again.'));
        }

        Cache::forget($cacheKey);

        // Validate the credential structure
        if (empty($credential['id']) || empty($credential['response']['attestationObject']) || empty($credential['response']['clientDataJSON'])) {
            throw new \RuntimeException(__('Invalid credential format'));
        }

        // Decode and validate clientDataJSON
        $clientDataJSON = base64_decode(strtr($credential['response']['clientDataJSON'], '-_', '+/'));
        $clientData = json_decode($clientDataJSON, true);

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException(__('Invalid ceremony type'));
        }

        if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
            throw new \RuntimeException(__('Challenge mismatch'));
        }

        $expectedOrigin = rtrim(config('app.frontend_url', config('app.url')), '/');
        if (($clientData['origin'] ?? '') !== $expectedOrigin) {
            throw new \RuntimeException(__('Origin mismatch'));
        }

        $webAuthnCredential = WebAuthnCredential::create([
            'user_id' => $user->id,
            'name' => $name,
            'credential_id' => $credential['id'],
            'public_key' => Crypt::encryptString($credential['response']['attestationObject']),
            'attestation_type' => 'none',
            'transports' => $credential['response']['transports'] ?? [],
            'sign_count' => 0,
            'is_discoverable' => true,
        ]);

        // Enable passkey on user if first credential
        if (!$user->passkey_enabled) {
            $user->update(['passkey_enabled' => true]);
        }

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::PASSKEY_REGISTERED,
            request: $request,
            metadata: ['credential_name' => $name],
        );

        return $webAuthnCredential;
    }

    /**
     * Generate authentication options for passkey login.
     */
    public function generateAuthenticationOptions(?string $email = null): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

        $sessionId = Str::uuid()->toString();
        Cache::put("webauthn_auth_{$sessionId}", $challengeB64, 300);

        $allowCredentials = [];

        if ($email) {
            $user = User::where('email', strtolower($email))->first();
            if ($user) {
                $allowCredentials = $user->webAuthnCredentials->map(fn(WebAuthnCredential $cred) => [
                    'type' => 'public-key',
                    'id' => $cred->credential_id,
                    'transports' => $cred->transports ?? [],
                ])->toArray();
            }
        }

        return [
            'challenge' => $challengeB64,
            'rpId' => parse_url(config('app.frontend_url', config('app.url')), PHP_URL_HOST),
            'timeout' => 60000,
            'userVerification' => 'required',
            'allowCredentials' => $allowCredentials,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Verify a passkey authentication assertion.
     */
    public function verifyAuthentication(string $sessionId, array $assertion, Request $request): User
    {
        $expectedChallenge = Cache::get("webauthn_auth_{$sessionId}");

        if (!$expectedChallenge) {
            throw new \RuntimeException(__('Authentication challenge expired. Please try again.'));
        }

        Cache::forget("webauthn_auth_{$sessionId}");

        if (empty($assertion['id']) || empty($assertion['response']['authenticatorData']) || empty($assertion['response']['clientDataJSON']) || empty($assertion['response']['signature'])) {
            throw new \RuntimeException(__('Invalid assertion format'));
        }

        // Decode and validate clientDataJSON
        $clientDataJSON = base64_decode(strtr($assertion['response']['clientDataJSON'], '-_', '+/'));
        $clientData = json_decode($clientDataJSON, true);

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \RuntimeException(__('Invalid ceremony type'));
        }

        if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
            throw new \RuntimeException(__('Challenge mismatch'));
        }

        $expectedOrigin = rtrim(config('app.frontend_url', config('app.url')), '/');
        if (($clientData['origin'] ?? '') !== $expectedOrigin) {
            throw new \RuntimeException(__('Origin mismatch'));
        }

        // Find the credential
        $credential = WebAuthnCredential::where('credential_id', $assertion['id'])->first();

        if (!$credential) {
            throw new \RuntimeException(__('Unknown credential'));
        }

        // Update sign count and last used
        $credential->update([
            'sign_count' => $credential->sign_count + 1,
            'last_used_at' => now(),
        ]);

        $user = User::findOrFail($credential->user_id);

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::PASSKEY_LOGIN,
            request: $request,
            metadata: ['credential_name' => $credential->name],
        );

        return $user;
    }

    /**
     * Remove a passkey credential.
     */
    public function removeCredential(User $user, int $credentialId, Request $request): void
    {
        $credential = WebAuthnCredential::where('id', $credentialId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $credential->delete();

        // Disable passkey if no credentials remain
        if ($user->webAuthnCredentials()->count() === 0) {
            $user->update(['passkey_enabled' => false]);
        }

        $this->auditService->log(
            userId: $user->id,
            action: MfaAuditAction::PASSKEY_REMOVED,
            request: $request,
            metadata: ['credential_name' => $credential->name],
        );
    }

    /**
     * List user's passkey credentials (sanitized).
     */
    public function listCredentials(User $user): array
    {
        return $user->webAuthnCredentials()
            ->select(['id', 'name', 'is_discoverable', 'last_used_at', 'created_at'])
            ->orderByDesc('last_used_at')
            ->get()
            ->toArray();
    }
}
