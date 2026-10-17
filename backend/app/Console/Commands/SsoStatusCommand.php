<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
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
            $this->warnAboutPasswordOnlyUsers($userRepository);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function warnAboutPasswordOnlyUsers(UserRepositoryInterface $userRepository): void
    {
        $count = $userRepository->countWithoutSingleSignOnExcludingSuperAdmins();

        if ($count > 0) {
            $this->newLine();
            $this->warn("$count users have not signed in with single sign-on yet. They can sign in once their provider email is verified and matches their account.");
        }
    }
}
