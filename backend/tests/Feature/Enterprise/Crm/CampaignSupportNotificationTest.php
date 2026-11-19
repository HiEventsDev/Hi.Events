<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPausedJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Mail\CampaignPausedMail;
use HiEvents\Enterprise\Crm\Mail\CampaignPendingReviewMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignSupportNotificationTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => true]);
        $this->setUpCrmApi();
    }

    public static function addressesNobodyReads(): array
    {
        return [
            'never configured, the shipped default' => ['support@example.com'],
            'the shipped default in another case' => ['Support@Example.COM'],
            'another reserved example domain' => ['help@example.org'],
            'left empty' => [''],
            'not set at all' => [null],
        ];
    }

    #[DataProvider('addressesNobodyReads')]
    public function test_a_review_request_is_never_mailed_to_an_address_nobody_reads_and_stays_in_the_review_queue(?string $supportEmail): void
    {
        Mail::fake();
        config(['app.platform_support_email' => $supportEmail]);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'name' => 'Needs a look']);

        app()->call([new NotifyCampaignPendingReviewJob($campaignId, 12, 'FIRST_CAMPAIGN'), 'handle']);

        Mail::assertNothingQueued();
        $queued = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)))->assertOk()->json('data'));
        $this->assertNotNull($queued->firstWhere('id', $campaignId));
    }

    #[DataProvider('addressesNobodyReads')]
    public function test_a_stopped_campaign_still_tells_its_creator_when_there_is_no_support_inbox_to_tell(?string $supportEmail): void
    {
        Mail::fake();
        config(['app.platform_support_email' => $supportEmail]);
        $campaignId = $this->insertCampaign(['status' => 'PAUSED', 'status_reason' => 'COMPLAINT_RATE']);

        app()->call([new NotifyCampaignPausedJob($campaignId), 'handle']);

        Mail::assertQueued(CampaignNotSentMail::class, 1);
        Mail::assertNotQueued(CampaignPausedMail::class);
    }

    public function test_a_configured_support_inbox_gets_both_notifications(): void
    {
        Mail::fake();
        config(['app.platform_support_email' => ' reviews@tickets.example.net.test ']);
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW']);
        $pausedId = $this->insertCampaign(['status' => 'PAUSED', 'status_reason' => 'COMPLAINT_RATE']);

        app()->call([new NotifyCampaignPendingReviewJob($pendingId, 12, 'FIRST_CAMPAIGN'), 'handle']);
        app()->call([new NotifyCampaignPausedJob($pausedId), 'handle']);

        Mail::assertQueued(CampaignPendingReviewMail::class, static fn (CampaignPendingReviewMail $mail) => $mail->hasTo('reviews@tickets.example.net.test'));
        Mail::assertQueued(CampaignPausedMail::class, static fn (CampaignPausedMail $mail) => $mail->hasTo('reviews@tickets.example.net.test'));
    }
}
