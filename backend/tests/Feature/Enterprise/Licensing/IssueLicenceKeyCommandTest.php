<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Licensing;

use HiEvents\Enterprise\Licensing\LicenceKeyCodec;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\Plan;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IssueLicenceKeyCommandTest extends TestCase
{
    private string $privateKeyFile;

    private LicenceKeyCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->privateKeyFile = tempnam(sys_get_temp_dir(), 'licence-key-');
        file_put_contents($this->privateKeyFile, base64_encode(sodium_crypto_sign_secretkey($keypair)));
        $this->codec = new LicenceKeyCodec(['test' => base64_encode(sodium_crypto_sign_publickey($keypair))]);
        $this->app->instance(LicenceKeyCodec::class, $this->codec);
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyFile);

        parent::tearDown();
    }

    public function test_an_own_events_key_carries_its_covered_sales_and_add_ons(): void
    {
        $this->assertSame(0, $this->issue(['--covers' => '20000', '--add' => ['seating', 'box_office', 'enterprise_sso']]));

        $licence = $this->codec->decode(trim(Artisan::output()));

        $this->assertSame(Plan::OWN_EVENTS, $licence->plan);
        $this->assertSame(20000, $licence->covered_sales);
        $this->assertSame(
            [LicensedFeature::WHITE_LABEL, LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE, LicensedFeature::ENTERPRISE_SSO],
            $licence->features,
        );
    }

    public function test_a_cloud_key_needs_no_covered_sales(): void
    {
        $this->assertSame(0, $this->issue(['--plan' => 'cloud']));

        $licence = $this->codec->decode(trim(Artisan::output()));

        $this->assertSame(Plan::CLOUD, $licence->plan);
        $this->assertNull($licence->covered_sales);
    }

    public function test_self_hosted_plans_require_covered_sales(): void
    {
        foreach (['own_events', 'platform'] as $plan) {
            $this->assertSame(1, $this->issue(['--plan' => $plan]));
            $this->assertStringContainsString("--covers is required for the $plan plan", Artisan::output());
        }
    }

    public function test_covered_sales_must_be_a_whole_number_of_euros_above_zero(): void
    {
        foreach (['0', '-5', '1.5', 'lots'] as $covers) {
            $this->assertSame(1, $this->issue(['--covers' => $covers]));
            $this->assertStringContainsString('--covers must be a whole number of euros above zero', Artisan::output());
        }
    }

    public function test_a_cloud_key_cannot_cover_sales(): void
    {
        $this->assertSame(1, $this->issue(['--plan' => 'cloud', '--covers' => '20000']));
        $this->assertStringContainsString('--covers can only be used with the own_events and platform plans', Artisan::output());
    }

    public function test_the_retired_enterprise_plan_cannot_be_issued(): void
    {
        $this->assertSame(1, $this->issue(['--plan' => 'enterprise', '--covers' => '20000']));
        $this->assertStringContainsString('Unknown plan. Valid plans: own_events, platform, cloud', Artisan::output());
    }

    private function issue(array $options): int
    {
        return Artisan::call('ee:licence:issue', [
            '--private-key' => $this->privateKeyFile,
            '--kid' => 'test',
            '--customer' => 'Riverside Theatre',
            '--expires' => '2027-10-01',
            ...$options,
        ]);
    }
}
