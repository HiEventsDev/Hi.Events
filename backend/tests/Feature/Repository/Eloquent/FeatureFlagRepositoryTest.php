<?php

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Models\User;
use HiEvents\Repository\DTO\AccountFeatureFlagDTO;
use HiEvents\Repository\Interfaces\FeatureFlagRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class FeatureFlagRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    private FeatureFlagRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(FeatureFlagRepositoryInterface::class);
        $this->setFeatureFlagDefault(FeatureFlag::SEATING, false);
    }

    public function test_find_for_account_only_applies_that_accounts_override(): void
    {
        $accountId = $this->makeAccount();
        $otherAccountId = $this->makeAccount();
        $this->setFeatureFlagOverride($otherAccountId, FeatureFlag::SEATING, true);

        $flag = $this->seatingFor($accountId);
        $this->assertFalse($flag->enabledByDefault);
        $this->assertNull($flag->override);
        $this->assertFalse($flag->isEnabled());

        $this->assertTrue($this->seatingFor($otherAccountId)->isEnabled());
    }

    public function test_find_for_account_returns_disabled_override(): void
    {
        $accountId = $this->makeAccount();
        $this->setFeatureFlagDefault(FeatureFlag::SEATING, true);
        $this->setFeatureFlagOverride($accountId, FeatureFlag::SEATING, false);

        $flag = $this->seatingFor($accountId);
        $this->assertFalse($flag->override);
        $this->assertFalse($flag->isEnabled());
    }

    public function test_find_all_with_override_counts_splits_enabled_and_disabled(): void
    {
        $before = $this->repository->findAllWithOverrideCounts()->firstWhere('key', 'seating');

        $this->setFeatureFlagOverride($this->makeAccount(), FeatureFlag::SEATING, true);
        $this->setFeatureFlagOverride($this->makeAccount(), FeatureFlag::SEATING, true);
        $this->setFeatureFlagOverride($this->makeAccount(), FeatureFlag::SEATING, false);

        $after = $this->repository->findAllWithOverrideCounts()->firstWhere('key', 'seating');
        $this->assertSame($before->enabledOverrideCount + 2, $after->enabledOverrideCount);
        $this->assertSame($before->disabledOverrideCount + 1, $after->disabledOverrideCount);
    }

    public function test_override_counts_ignore_soft_deleted_accounts(): void
    {
        $before = $this->repository->findAllWithOverrideCounts()->firstWhere('key', 'seating');

        $deletedAccountId = $this->makeAccount();
        $this->setFeatureFlagOverride($deletedAccountId, FeatureFlag::SEATING, true);
        DB::table('accounts')->where('id', $deletedAccountId)->update(['deleted_at' => now()]);

        $after = $this->repository->findAllWithOverrideCounts()->firstWhere('key', 'seating');
        $this->assertSame($before->enabledOverrideCount, $after->enabledOverrideCount);
    }

    private function seatingFor(int $accountId): AccountFeatureFlagDTO
    {
        return $this->repository->findForAccount($accountId)->firstWhere('key', 'seating');
    }

    private function makeAccount(): int
    {
        return User::factory()->withAccount()->create()->accounts()->first()->id;
    }
}
