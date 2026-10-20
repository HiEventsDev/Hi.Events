<?php

namespace Tests\Unit\Enterprise\Licensing;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\LicenceKeyCodec;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicenceStatus;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class LicenceServiceTest extends TestCase
{
    private string $secretKey;

    private LicenceKeyCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $this->codec = new LicenceKeyCodec(['test-a' => base64_encode(sodium_crypto_sign_publickey($keypair))]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_no_key_unlocks_nothing_even_on_a_local_install(): void
    {
        foreach (['production', 'local'] as $env) {
            $service = $this->service(key: null, env: $env);

            $this->assertSame(LicenceStatus::NONE, $service->status());
            foreach (LicensedFeature::cases() as $feature) {
                $this->assertFalse($service->allows($feature));
            }
        }
    }

    public function test_no_key_in_a_test_environment_unlocks_everything_except_white_label(): void
    {
        foreach (['testing', 'e2e'] as $env) {
            $service = $this->service(key: null, env: $env);

            $this->assertSame(LicenceStatus::DEV, $service->status());
            $this->assertTrue($service->allows(LicensedFeature::SEATING));
            $this->assertTrue($service->allows(LicensedFeature::BOX_OFFICE));
            $this->assertFalse($service->allows(LicensedFeature::WHITE_LABEL));
        }
    }

    public function test_the_development_key_unlocks_everything_except_white_label_in_any_environment(): void
    {
        foreach (['production', 'local'] as $env) {
            $service = $this->service(key: 'development', env: $env);

            $this->assertSame(LicenceStatus::DEV, $service->status());
            $this->assertTrue($service->allows(LicensedFeature::SEATING));
            $this->assertTrue($service->allows(LicensedFeature::BOX_OFFICE));
            $this->assertFalse($service->allows(LicensedFeature::WHITE_LABEL));
            $this->assertNull($service->state()->invalid_reason);
        }
    }

    public function test_only_a_cloud_plan_key_marks_the_install_as_hi_events_cloud(): void
    {
        CarbonImmutable::setTestNow('2027-01-01');
        $cloud = $this->service($this->issue(plan: 'cloud'));

        $this->assertTrue($cloud->isCloud());
        $this->assertSame(LicenceStatus::ACTIVE, $cloud->status());
        $this->assertTrue($cloud->allows(LicensedFeature::SEATING));
        $this->assertTrue($cloud->allows(LicensedFeature::BOX_OFFICE));
        $this->assertFalse($cloud->allows(LicensedFeature::WHITE_LABEL));
        $this->assertFalse($this->service($this->issue())->isCloud());
        $this->assertFalse($this->service('development')->isCloud());
        $this->assertFalse($this->service(null, env: 'testing')->isCloud());
    }

    public function test_a_valid_key_is_active_until_it_expires(): void
    {
        CarbonImmutable::setTestNow('2027-09-30 23:59:59');
        $service = $this->service($this->issue());

        $this->assertSame(LicenceStatus::ACTIVE, $service->status());
        $this->assertTrue($service->allows(LicensedFeature::SEATING));
        $this->assertTrue($service->allows(LicensedFeature::BOX_OFFICE));
        $this->assertFalse($service->allows(LicensedFeature::WHITE_LABEL));
    }

    public function test_a_key_in_its_grace_period_still_unlocks_its_features(): void
    {
        CarbonImmutable::setTestNow('2027-10-30 12:00:00');
        $service = $this->service($this->issue());

        $this->assertSame(LicenceStatus::GRACE, $service->status());
        $this->assertTrue($service->allows(LicensedFeature::SEATING));
        $this->assertSame('2027-10-31', $service->state()->grace_ends_at->toDateString());
    }

    public function test_a_key_past_its_grace_period_lapses(): void
    {
        CarbonImmutable::setTestNow('2027-10-31 00:00:00');
        $service = $this->service($this->issue());

        $this->assertSame(LicenceStatus::LAPSED, $service->status());
        $this->assertFalse($service->allows(LicensedFeature::SEATING));
        $this->assertSame('lic_test', $service->state()->licence->lid);
    }

    public function test_a_key_in_development_is_still_evaluated(): void
    {
        CarbonImmutable::setTestNow('2030-01-01');

        $this->assertSame(LicenceStatus::LAPSED, $this->service($this->issue(), env: 'local')->status());
    }

    public function test_add_ons_unlock_white_label(): void
    {
        CarbonImmutable::setTestNow('2027-01-01');

        $this->assertTrue($this->service($this->issue(['white_label']))->allows(LicensedFeature::WHITE_LABEL));
    }

    public function test_an_invalid_key_behaves_like_no_key_and_reports_the_reason_once(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once();

        $key = 'hiev1.garbage.'.uniqid();
        $cache = new CacheRepository(new ArrayStore);
        $first = new LicenceService($this->codec, $this->config($key, 'local'), $logger, $cache);
        $second = new LicenceService($this->codec, $this->config($key, 'local'), $logger, $cache);

        $this->assertSame(LicenceStatus::NONE, $first->status());
        $this->assertNotNull($first->state()->invalid_reason);
        $this->assertFalse($first->allows(LicensedFeature::SEATING));
        $this->assertSame(LicenceStatus::NONE, $second->status());
    }

    public function test_a_broken_cache_never_stops_the_licence_being_read(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once();
        $cache = Mockery::mock(CacheContract::class);
        $cache->shouldReceive('add')->andThrow(new RuntimeException('Cache unavailable'));

        $service = new LicenceService($this->codec, $this->config('hiev1.garbage.'.uniqid(), 'production'), $logger, $cache);

        $this->assertSame(LicenceStatus::NONE, $service->status());
    }

    public function test_a_simulated_state_replaces_the_real_one_outside_production(): void
    {
        $this->assertSame(LicenceStatus::ACTIVE, $this->simulated('ACTIVE')->status());
        $this->assertSame(LicenceStatus::ACTIVE, $this->simulated('EXPIRING')->status());
        $this->assertSame(LicenceStatus::GRACE, $this->simulated('GRACE')->status());
        $this->assertTrue($this->simulated('GRACE')->allows(LicensedFeature::SEATING));
        $this->assertSame(LicenceStatus::LAPSED, $this->simulated('LAPSED')->status());
        $this->assertFalse($this->simulated('LAPSED')->allows(LicensedFeature::SEATING));
        $this->assertSame(LicenceStatus::NONE, $this->simulated('NONE')->status());
        $this->assertNotNull($this->simulated('INVALID')->state()->invalid_reason);
        $this->assertSame(LicenceStatus::DEV, $this->simulated('DEV', key: $this->issue())->status());
    }

    public function test_hi_events_cloud_can_be_simulated(): void
    {
        $cloud = $this->simulated('CLOUD');

        $this->assertSame(LicenceStatus::ACTIVE, $cloud->status());
        $this->assertTrue($cloud->isCloud());
        $this->assertTrue($cloud->allows(LicensedFeature::SEATING));
        $this->assertFalse($this->simulated('ACTIVE')->isCloud());
    }

    public function test_a_simulated_state_can_name_its_features(): void
    {
        $service = $this->simulated('ACTIVE:white_label');

        $this->assertTrue($service->allows(LicensedFeature::WHITE_LABEL));
        $this->assertFalse($service->allows(LicensedFeature::SEATING));
    }

    public function test_an_unknown_simulated_state_falls_back_to_the_real_one(): void
    {
        $this->assertSame(LicenceStatus::DEV, $this->simulated('BOGUS')->status());
    }

    public function test_a_simulated_state_is_ignored_on_a_local_install(): void
    {
        $this->assertSame(LicenceStatus::NONE, $this->simulated('ACTIVE:white_label', env: 'local')->status());
        $this->assertSame(LicenceStatus::DEV, $this->simulated('ACTIVE:white_label', env: 'local', key: 'development')->status());
    }

    public function test_a_simulated_state_is_ignored_in_production(): void
    {
        $this->assertSame(LicenceStatus::NONE, $this->simulated('ACTIVE:white_label', env: 'production')->status());
    }

    private function simulated(string $simulation, string $env = 'e2e', ?string $key = null): LicenceService
    {
        return new LicenceService(
            $this->codec,
            $this->config($key, $env, $simulation),
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            new CacheRepository(new ArrayStore),
        );
    }

    private function service(?string $key, string $env = 'production'): LicenceService
    {
        return new LicenceService($this->codec, $this->config($key, $env), Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(), new CacheRepository(new ArrayStore));
    }

    private function config(?string $key, string $env, ?string $simulation = null): Repository
    {
        return new Repository(['licence' => ['key' => $key, 'simulation' => $simulation], 'app' => ['env' => $env]]);
    }

    private function issue(array $addOns = [], string $plan = 'enterprise'): string
    {
        return $this->codec->encode([
            'kid' => 'test-a',
            'lid' => 'lic_test',
            'customer' => 'Riverside Theatre',
            'issued_at' => '2026-10-01',
            'expires_at' => '2027-10-01',
            'plan' => $plan,
            'add' => $addOns,
        ], $this->secretKey);
    }
}
