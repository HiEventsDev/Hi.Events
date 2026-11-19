<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\PruneCrmBulkOperationsJob;
use HiEvents\Enterprise\Crm\Jobs\PruneCrmPendingSubscriptionsJob;
use HiEvents\Enterprise\Crm\Jobs\ReconcileContactsJob;
use HiEvents\Enterprise\Crm\Jobs\RecordCampaignFeedbackJob;
use HiEvents\Enterprise\Crm\Jobs\RunAutomationsJob;
use HiEvents\Enterprise\Crm\Jobs\SendScheduledCampaignsJob;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmSchedulerTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    public static function scheduledJobs(): array
    {
        return [
            'contact reconcile needs a licence' => [ReconcileContactsJob::class, '0 * * * *', false],
            'campaign feedback needs a licence' => [RecordCampaignFeedbackJob::class, '* * * * *', false],
            'automations need a licence' => [RunAutomationsJob::class, '*/15 * * * *', false],
            'the scheduled campaign sweep always runs' => [SendScheduledCampaignsJob::class, '* * * * *', true],
            'bulk operation pruning always runs' => [PruneCrmBulkOperationsJob::class, '0 0 * * *', true],
            'pending signup pruning always runs' => [PruneCrmPendingSubscriptionsJob::class, '0 0 * * *', true],
        ];
    }

    #[DataProvider('scheduledJobs')]
    public function test_each_crm_job_is_scheduled_and_runs_without_a_licence_only_when_it_tidies_up(string $job, string $expression, bool $runsWithoutLicence): void
    {
        $this->useCloudLicence();
        $scheduled = $this->scheduled($job);
        $this->assertSame($expression, $scheduled->expression);
        $this->assertTrue($scheduled->filtersPass($this->app));

        $this->useLicenceKey(null);
        $this->assertSame($runsWithoutLicence, $this->scheduled($job)->filtersPass($this->app));
    }

    public function test_without_a_licence_a_scheduled_campaign_past_its_window_goes_back_to_draft_and_one_inside_it_waits(): void
    {
        $this->setUpCrmOrganizer();
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $window = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60;
        $expiredId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes($window + 5)]);
        $waitingId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes($window - 5)]);
        $this->useLicenceKey(null);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame(['DRAFT', 'SCHEDULED'], [$this->campaign($expiredId)->status, $this->campaign($waitingId)->status]);
        Mail::assertQueued(CampaignNotSentMail::class, 1);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public static function jobsRunOnDemand(): array
    {
        return [
            'automations' => [RunAutomationsJob::class],
            'scheduled campaigns' => [SendScheduledCampaignsJob::class],
        ];
    }

    #[DataProvider('jobsRunOnDemand')]
    public function test_a_scheduled_crm_job_can_be_run_on_demand_by_its_class_name(string $job): void
    {
        Bus::fake([$job]);

        $this->artisan('schedule:test', ['--name' => $job])->assertSuccessful();

        Bus::assertDispatchedTimes($job, 1);
    }

    private function scheduled(string $job): Event
    {
        return collect($this->app->make(Schedule::class)->events())
            ->sole(static fn (Event $event) => $event->description === $job);
    }
}
