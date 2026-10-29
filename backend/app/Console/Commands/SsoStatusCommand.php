<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\OidcClient;
use HiEvents\Services\Domain\Auth\Sso\OidcDiscoveryClient;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use Illuminate\Console\Command;

class SsoStatusCommand extends Command
{
    protected $signature = 'sso:status';

    protected $description = 'Show the configured single sign-on providers, the redirect URI to register with them, and whether they respond.';

    public function handle(
        SsoProviderRegistry $providerRegistry,
        OidcDiscoveryClient $discoveryClient,
        OidcClient $oidcClient,
        UserRepositoryInterface $userRepository,
    ): int {
        $providers = $providerRegistry->all();

        $this->line('Redirect URI to register with each provider: <info>'.$oidcClient->redirectUri().'</info>');
        $this->line('Password sign-in: '.($providerRegistry->isPasswordLoginDisabled() ? '<comment>disabled</comment>' : 'enabled'));
        $this->newLine();

        if ($providers === []) {
            $this->warn('No single sign-on providers are configured.');

            return self::SUCCESS;
        }

        $healthy = true;

        foreach ($providers as $provider) {
            try {
                $discoveryClient->discover($provider);
                $this->line("<info>✓</info> {$provider->name} ({$provider->key}) — {$provider->issuer}");
            } catch (SsoException $exception) {
                $healthy = false;
                $this->line("<error>✗</error> {$provider->name} ({$provider->key}) — {$exception->getMessage()}");
            }

            if ($provider->type === SsoProviderType::MICROSOFT) {
                $this->line('  Add the optional "xms_edov" ID token claim in the Microsoft app registration so verified work emails can be matched to existing accounts.');
            }
        }

        if ($providerRegistry->isPasswordLoginDisabled()) {
            $this->warnAboutPasswordOnlyUsers($userRepository, $providers);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  SsoProviderConfig[]  $providers
     */
    private function warnAboutPasswordOnlyUsers(UserRepositoryInterface $userRepository, array $providers): void
    {
        $count = $userRepository->countWithoutSingleSignOnExcludingSuperAdmins();

        if ($count === 0) {
            return;
        }

        $this->newLine();
        $this->warn("$count users have not signed in with single sign-on yet. With password sign-in disabled, a provider can only match them to their account by an email it vouches for:");

        foreach ($providers as $provider) {
            $this->line('  '.$this->emailMatchingRule($provider));
        }

        foreach ($providers as $provider) {
            if ($provider->type === SsoProviderType::OIDC && ! $provider->trustEmail) {
                $this->newLine();
                $this->error("{$provider->name} can't match these users to their accounts because SSO_OIDC_TRUST_EMAIL is off, so they can't sign in with it. Set SSO_OIDC_TRUST_EMAIL=true if {$provider->name} verifies email addresses, or turn password sign-in back on until they have linked {$provider->name} in their profile.");
            }
        }
    }

    private function emailMatchingRule(SsoProviderConfig $provider): string
    {
        return match ($provider->type) {
            SsoProviderType::GOOGLE => "{$provider->name}: verified Gmail addresses and Google Workspace accounts.",
            SsoProviderType::MICROSOFT => "{$provider->name}: work and school accounts, once the app registration sends the optional \"xms_edov\" claim. Personal Microsoft accounts never match.",
            SsoProviderType::OIDC => $provider->trustEmail
                ? "{$provider->name}: emails it marks as verified, because SSO_OIDC_TRUST_EMAIL is on."
                : "{$provider->name}: none, because SSO_OIDC_TRUST_EMAIL is off.",
        };
    }
}
