<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPausedJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHealthGuard;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignHealthGuardTest extends TestCase
{
    private CrmCampaignRepositoryInterface&MockInterface $campaigns;

    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaigns = Mockery::mock(CrmCampaignRepositoryInterface::class);
        $this->cache = new CacheRepository(new ArrayStore);
        Bus::fake([NotifyCampaignPausedJob::class]);
    }

    public static function healthySamples(): array
    {
        return [
            'nothing sent' => [0, 0, 0],
            'below the minimum sample with every email complained about' => [CampaignHealthGuard::MINIMUM_SENDS - 1, 49, 49],
            'no complaints or bounces' => [1000, 0, 0],
            'complaints exactly at the limit' => [1000, 3, 0],
            'bounces exactly at the limit' => [100, 0, 8],
        ];
    }

    #[DataProvider('healthySamples')]
    public function test_a_healthy_or_too_small_sample_is_left_sending(int $sent, int $complained, int $bounced): void
    {
        $this->stats($sent, $complained, $bounced);
        $this->campaigns->shouldNotReceive('transitionStatus');

        $this->assertFalse($this->guard()->stopIfUnhealthy(7));
        Bus::assertNotDispatched(NotifyCampaignPausedJob::class);
    }

    public function test_a_sample_too_small_to_judge_does_not_use_up_the_check_interval(): void
    {
        $this->campaigns->shouldReceive('getHealthStats')->once()->with(7)->andReturn((object) ['sent' => CampaignHealthGuard::MINIMUM_SENDS - 1, 'complained' => 0, 'bounced' => 0]);
        $this->campaigns->shouldReceive('getHealthStats')->once()->with(7)->andReturn((object) ['sent' => CampaignHealthGuard::MINIMUM_SENDS, 'complained' => 5, 'bounced' => 0]);
        $this->campaigns->shouldReceive('transitionStatus')->once()->andReturn(true);
        $this->campaigns->shouldReceive('skipPendingRecipients')->once()->with(7);
        $guard = $this->guard();

        $this->assertFalse($guard->stopIfUnhealthy(7));
        $this->assertTrue($guard->stopIfUnhealthy(7));
    }

    public static function unhealthySamples(): array
    {
        return [
            'complaints just over the limit' => [1000, 4, 0, CampaignHealthGuard::REASON_COMPLAINTS],
            'one complaint at the minimum sample' => [CampaignHealthGuard::MINIMUM_SENDS, 1, 0, CampaignHealthGuard::REASON_COMPLAINTS],
            'bounces just over the limit' => [100, 0, 9, CampaignHealthGuard::REASON_BOUNCES],
            'complaints are reported before bounces' => [100, 5, 50, CampaignHealthGuard::REASON_COMPLAINTS],
        ];
    }

    #[DataProvider('unhealthySamples')]
    public function test_an_unhealthy_campaign_is_stopped_only_while_it_is_sending(int $sent, int $complained, int $bounced, string $reason): void
    {
        $this->stats($sent, $complained, $bounced);
        $this->campaigns->shouldReceive('transitionStatus')
            ->once()
            ->with(7, ['SENDING'], ['status' => 'PAUSED', 'status_reason' => $reason])
            ->andReturn(true);
        $this->campaigns->shouldReceive('skipPendingRecipients')->once()->with(7);

        $this->assertTrue($this->guard()->stopIfUnhealthy(7));
        Bus::assertDispatched(NotifyCampaignPausedJob::class);
    }

    public function test_a_campaign_that_already_left_sending_is_not_reported_as_stopped(): void
    {
        $this->stats(100, 50, 0);
        $this->campaigns->shouldReceive('transitionStatus')->once()->andReturn(false);
        $this->campaigns->shouldNotReceive('skipPendingRecipients');

        $this->assertFalse($this->guard()->stopIfUnhealthy(7));
        Bus::assertNotDispatched(NotifyCampaignPausedJob::class);
    }

    public function test_a_campaign_is_checked_at_most_once_per_interval(): void
    {
        $this->campaigns->shouldReceive('getHealthStats')->once()->with(7)->andReturn((object) ['sent' => 100, 'complained' => 0, 'bounced' => 0]);
        $this->campaigns->shouldReceive('getHealthStats')->once()->with(8)->andReturn((object) ['sent' => 100, 'complained' => 0, 'bounced' => 0]);
        $guard = $this->guard();

        $this->assertFalse($guard->stopIfUnhealthy(7));
        $this->assertFalse($guard->stopIfUnhealthy(7));
        $this->assertFalse($guard->stopIfUnhealthy(8));

        $this->travel(CampaignHealthGuard::CHECK_INTERVAL_SECONDS + 1)->seconds();
        $this->campaigns->shouldReceive('getHealthStats')->once()->with(7)->andReturn((object) ['sent' => 100, 'complained' => 50, 'bounced' => 0]);
        $this->campaigns->shouldReceive('transitionStatus')->once()->andReturn(true);
        $this->campaigns->shouldReceive('skipPendingRecipients')->once();

        $this->assertTrue($guard->stopIfUnhealthy(7));
    }

    private function guard(): CampaignHealthGuard
    {
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('transaction')->andReturnUsing(static fn (callable $callback) => $callback());

        return new CampaignHealthGuard($this->campaigns, $this->cache, $database);
    }

    private function stats(int $sent, int $complained, int $bounced): void
    {
        $this->campaigns->shouldReceive('getHealthStats')
            ->with(7)
            ->andReturn((object) ['sent' => $sent, 'complained' => $complained, 'bounced' => $bounced]);
    }
}
