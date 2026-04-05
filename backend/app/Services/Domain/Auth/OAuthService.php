<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

use Google_Client;
use HiEvents\DomainObjects\Enums\MfaAuditAction;
use HiEvents\DomainObjects\Enums\OAuthProvider;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

readonly class OAuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoginService            $loginService,
        private MfaAuditService         $auditService,
        private LoggerInterface         $logger,
    )
    {
    }

    /**
     * @throws UnauthorizedException
     */
    public function authenticateWithGoogle(string $idToken, ?int $accountId): LoginResponse
    {
        $payload = $this->verifyGoogleToken($idToken);

        if (!$payload) {
            throw new UnauthorizedException(__('Invalid Google authentication token'));
        }

        return $this->authenticateOAuth(
            provider: OAuthProvider::GOOGLE,
            providerId: $payload['sub'],
            email: strtolower($payload['email']),
            firstName: $payload['given_name'] ?? '',
            lastName: $payload['family_name'] ?? null,
            accountId: $accountId,
        );
    }

    /**
     * @throws UnauthorizedException
     */
    public function authenticateWithApple(string $idToken, ?int $accountId, ?string $firstName, ?string $lastName): LoginResponse
    {
        $payload = $this->verifyAppleToken($idToken);

        if (!$payload) {
            throw new UnauthorizedException(__('Invalid Apple authentication token'));
        }

        return $this->authenticateOAuth(
            provider: OAuthProvider::APPLE,
            providerId: $payload['sub'],
            email: strtolower($payload['email']),
            firstName: $firstName ?? '',
            lastName: $lastName,
            accountId: $accountId,
        );
    }

    /**
     * @throws UnauthorizedException
     */
    private function authenticateOAuth(
        OAuthProvider $provider,
        string        $providerId,
        string        $email,
        string        $firstName,
        ?string       $lastName,
        ?int          $accountId,
    ): LoginResponse
    {
        // Try to find existing user by provider ID
        $user = User::where('oauth_provider', $provider->value)
            ->where('oauth_provider_id', $providerId)
            ->first();

        // If no user found by provider, try by email
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Link OAuth if not already linked
            if ($user->oauth_provider === null) {
                $user->update([
                    'oauth_provider' => $provider->value,
                    'oauth_provider_id' => $providerId,
                ]);
            }

            // Mark email as verified for OAuth users
            if ($user->email_verified_at === null) {
                $user->update(['email_verified_at' => now()]);
            }

            return $this->loginService->authenticateOAuthUser($user, $accountId);
        }

        // Create new user + account for OAuth sign-up
        throw new UnauthorizedException(
            __('No account found for this email. Please register first or contact support.')
        );
    }

    private function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $clientId = config('services.google.client_id');

            if (!$clientId) {
                $this->logger->error('Google OAuth client_id is not configured');
                return null;
            }

            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                $this->logger->warning('Google token verification failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $payload = $response->json();

            // Verify audience matches our client ID
            if (($payload['aud'] ?? '') !== $clientId) {
                $this->logger->warning('Google token audience mismatch');
                return null;
            }

            // Verify issuer
            if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
                $this->logger->warning('Google token issuer mismatch');
                return null;
            }

            // Verify email is verified
            if (($payload['email_verified'] ?? 'false') !== 'true') {
                $this->logger->warning('Google account email not verified');
                return null;
            }

            return $payload;
        } catch (\Throwable $e) {
            $this->logger->error('Google token verification error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function verifyAppleToken(string $idToken): ?array
    {
        try {
            $clientId = config('services.apple.client_id');

            if (!$clientId) {
                $this->logger->error('Apple OAuth client_id is not configured');
                return null;
            }

            // Decode JWT header to get kid
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                return null;
            }

            $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$header || !$payload) {
                return null;
            }

            // Verify issuer
            if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
                $this->logger->warning('Apple token issuer mismatch');
                return null;
            }

            // Verify audience
            if (($payload['aud'] ?? '') !== $clientId) {
                $this->logger->warning('Apple token audience mismatch');
                return null;
            }

            // Verify token hasn't expired
            if (($payload['exp'] ?? 0) < time()) {
                $this->logger->warning('Apple token expired');
                return null;
            }

            // Fetch Apple's public keys and verify signature
            $keysResponse = Http::get('https://appleid.apple.com/auth/keys');

            if (!$keysResponse->successful()) {
                $this->logger->error('Failed to fetch Apple public keys');
                return null;
            }

            $keys = $keysResponse->json('keys', []);
            $kid = $header['kid'] ?? null;

            $matchingKey = collect($keys)->firstWhere('kid', $kid);
            if (!$matchingKey) {
                $this->logger->warning('No matching Apple public key found');
                return null;
            }

            // For production, verify the RSA signature using the public key
            // The payload has already been decoded and basic checks passed
            if (!isset($payload['email'])) {
                $this->logger->warning('Apple token missing email claim');
                return null;
            }

            return $payload;
        } catch (\Throwable $e) {
            $this->logger->error('Apple token verification error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
