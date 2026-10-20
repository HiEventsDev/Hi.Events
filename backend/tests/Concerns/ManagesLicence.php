<?php

namespace Tests\Concerns;

use HiEvents\Enterprise\Licensing\LicenceKeyCodec;
use HiEvents\Enterprise\Licensing\Plan;

trait ManagesLicence
{
    protected function useLicence(?string $expiresAt, string $env = 'production', array $addOns = [], Plan $plan = Plan::ENTERPRISE): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $codec = new LicenceKeyCodec(['test' => base64_encode(sodium_crypto_sign_publickey($keypair))]);
        $this->app->instance(LicenceKeyCodec::class, $codec);

        $key = $expiresAt === null ? null : $codec->encode([
            'kid' => 'test',
            'lid' => 'lic_test',
            'customer' => 'Test customer',
            'issued_at' => '2019-01-01',
            'expires_at' => $expiresAt,
            'plan' => $plan->value,
            'add' => $addOns,
        ], base64_encode(sodium_crypto_sign_secretkey($keypair)));

        $this->useLicenceKey($key, $env);
    }

    protected function useCloudLicence(): void
    {
        $this->useLicence(expiresAt: '2099-12-31', env: config('app.env'), plan: Plan::CLOUD);
    }

    protected function useLicenceKey(?string $key, string $env = 'production'): void
    {
        config(['app.env' => $env, 'licence.key' => $key]);
        $this->app->forgetScopedInstances();
    }
}
