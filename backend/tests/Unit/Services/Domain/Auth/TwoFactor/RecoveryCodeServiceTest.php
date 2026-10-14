<?php

namespace Tests\Unit\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use Mockery as m;
use Tests\TestCase;

class RecoveryCodeServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepository;

    private RecoveryCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = m::mock(UserRepositoryInterface::class);
        $this->service = new RecoveryCodeService($this->userRepository);
    }

    public function test_regenerate_returns_unique_readable_codes_and_stores_only_hashes(): void
    {
        $stored = null;
        $this->userRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes, array $where) use (&$stored) {
                $stored = json_decode($attributes['two_factor_recovery_codes'], true);

                return $where === ['id' => 7];
            })
            ->andReturn(1);

        $codes = $this->service->regenerate(7);

        $this->assertCount(RecoveryCodeService::CODE_COUNT, $codes);
        $this->assertCount(RecoveryCodeService::CODE_COUNT, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/', $code);
            $this->assertNotContains($code, $stored);
        }
        $this->assertSame(hash('sha256', str_replace('-', '', $codes[0])), $stored[0]);
    }

    public function test_consume_accepts_any_formatting_and_removes_the_code(): void
    {
        $storedValue = json_encode([hash('sha256', 'abcdefghjk'), hash('sha256', 'mnpqrstuvw')]);
        $user = (new UserDomainObject)->setId(3)->setTwoFactorRecoveryCodes($storedValue);

        $this->userRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['two_factor_recovery_codes' => json_encode([hash('sha256', 'mnpqrstuvw')])],
                ['id' => 3, 'two_factor_recovery_codes' => $storedValue],
            )
            ->andReturn(1);

        $this->assertTrue($this->service->consume($user, ' ABCDE fghjk '));
    }

    public function test_consume_rejects_unknown_codes_without_writing(): void
    {
        $user = (new UserDomainObject)->setId(3)->setTwoFactorRecoveryCodes(json_encode([hash('sha256', 'abcdefghjk')]));

        $this->userRepository->shouldNotReceive('updateWhere');

        $this->assertFalse($this->service->consume($user, 'zzzzz-zzzzz'));
    }

    public function test_consume_fails_when_a_concurrent_request_already_used_the_code(): void
    {
        $user = (new UserDomainObject)->setId(3)->setTwoFactorRecoveryCodes(json_encode([hash('sha256', 'abcdefghjk')]));

        $this->userRepository->shouldReceive('updateWhere')->once()->andReturn(0);

        $this->assertFalse($this->service->consume($user, 'abcde-fghjk'));
    }

    public function test_remaining_counts_stored_codes(): void
    {
        $this->assertSame(0, $this->service->remaining(new UserDomainObject));
        $this->assertSame(2, $this->service->remaining(
            (new UserDomainObject)->setTwoFactorRecoveryCodes(json_encode(['a', 'b']))
        ));
    }
}
