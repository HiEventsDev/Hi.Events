<?php

namespace Tests\Feature\Console;

use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Support\SeedsResaleListings;
use Tests\TestCase;

class MaintainTicketResaleCommandTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsResaleListings;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ProcessResaleRefundJob::class]);
        Mail::fake();

        $this->seedResaleEvent();
    }

    public function test_it_closes_listings_that_can_no_longer_sell_and_leaves_the_rest_alone(): void
    {
        $openId = $this->insertListing(0);
        $withdrawnId = $this->insertListing(1, ['withdrawal_requested_at' => now()->subHour()]);
        $heldId = $this->insertListing(2, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1),
            'withdrawal_requested_at' => now()->subMinute(),
        ]);
        $pastCutoffId = $this->insertListingOnAnotherEvent(daysAhead: 0);
        $turnedOffId = $this->insertListingOnAnotherEvent(allowResale: false);

        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertListing($openId, TicketResaleListingStatus::LISTED, null);
        $this->assertListing($heldId, TicketResaleListingStatus::LISTED, null);
        $this->assertListing($withdrawnId, TicketResaleListingStatus::CLOSED, TicketResaleCloseReason::WITHDRAWN);
        $this->assertListing($pastCutoffId, TicketResaleListingStatus::CLOSED, TicketResaleCloseReason::CUTOFF_PASSED);
        $this->assertListing($turnedOffId, TicketResaleListingStatus::CLOSED, TicketResaleCloseReason::RESALE_TURNED_OFF);
        $this->assertSame(3, DB::table('order_audit_logs')->where('action', OrderAuditAction::TICKET_RESALE_CLOSED->value)->count());
        $sellerNotices = Mail::queued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::SELLER_EMAIL));
        $holderNotices = Mail::queued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => ! $mail->hasTo(self::SELLER_EMAIL));
        $this->assertSame(
            [TicketResaleNotice::UNSOLD_FOR_SELLER->subject(), TicketResaleNotice::UNSOLD_FOR_SELLER->subject()],
            $sellerNotices->map(fn (TicketResaleNoticeMail $mail) => $mail->envelope()->subject)->values()->all(),
        );
        $this->assertSame(
            array_fill(0, 3, TicketResaleNotice::RETURNED_FOR_HOLDER->subject()),
            $holderNotices->map(fn (TicketResaleNoticeMail $mail) => $mail->envelope()->subject)->values()->all(),
        );
    }

    public function test_it_restarts_a_refund_that_never_started_once_nothing_has_touched_it_for_longer_than_the_longest_retry_gap(): void
    {
        $stuckId = $this->insertListing(0, $this->awaitingRefund(['updated_at' => now()->subMinutes(80)]));
        $this->insertListing(1, $this->awaitingRefund(['updated_at' => now()->subMinutes(70)]));
        $this->insertListing(2, $this->awaitingRefund(['updated_at' => now()->subHours(3), 'stripe_refund_id' => 're_started']));
        $this->insertListing(3, $this->awaitingRefund([
            'updated_at' => now()->subHours(3),
            'refund_status' => TicketResaleRefundStatus::FAILED->name,
        ]));

        $this->artisan('ticket-resale:maintain')->assertSuccessful();
        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        Queue::assertPushed(ProcessResaleRefundJob::class, 1);
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $stuckId,
            'refund_status' => TicketResaleRefundStatus::PENDING->name,
            'refund_attempts' => 0,
        ]);

        DB::table('ticket_resale_listings')->where('id', $stuckId)->update(['updated_at' => now()->subMinutes(76)]);
        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        Queue::assertPushed(ProcessResaleRefundJob::class, 2);
    }

    public function test_it_prunes_verifications_that_expired_more_than_a_day_ago(): void
    {
        $oldCodeId = $this->insertVerification(['code_hash' => 'old-code', 'code_expires_at' => now()->subDays(2)], createdAt: now()->subDays(2));
        $oldSessionId = $this->insertVerification(['session_token_hash' => 'old-session', 'session_expires_at' => now()->subDays(3)], createdAt: now()->subDays(3));
        $recentlyExpiredId = $this->insertVerification(['session_token_hash' => 'recent-session', 'session_expires_at' => now()->subHours(2)], createdAt: now()->subHours(3));
        $liveCodeId = $this->insertVerification(['code_hash' => 'live-code', 'code_expires_at' => now()->addMinutes(5)], createdAt: now());

        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertDatabaseMissing('self_service_verifications', ['id' => $oldCodeId]);
        $this->assertDatabaseMissing('self_service_verifications', ['id' => $oldSessionId]);
        $this->assertDatabaseHas('self_service_verifications', ['id' => $recentlyExpiredId]);
        $this->assertDatabaseHas('self_service_verifications', ['id' => $liveCodeId]);
    }

    public function test_running_it_again_changes_nothing_more(): void
    {
        $this->insertListing(0);
        $this->insertListing(1, ['withdrawal_requested_at' => now()->subHour()]);
        $this->insertListing(2, $this->awaitingRefund(['updated_at' => now()->subMinutes(80)]));
        $this->insertListingOnAnotherEvent(daysAhead: 0);
        $this->insertVerification(['code_hash' => 'old-code', 'code_expires_at' => now()->subDays(2)], createdAt: now()->subDays(2));

        $this->artisan('ticket-resale:maintain')->assertSuccessful();
        $afterFirstRun = $this->snapshot();

        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertSame($afterFirstRun, $this->snapshot());
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::SELLER_EMAIL), 1);
        Mail::assertQueued(TicketResaleNoticeMail::class, 3);
        Queue::assertPushed(ProcessResaleRefundJob::class, 1);
    }

    private function awaitingRefund(array $attributes): array
    {
        return array_merge([
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::PENDING->name,
        ], $attributes);
    }

    private function insertVerification(array $attributes, \DateTimeInterface $createdAt): int
    {
        return DB::table('self_service_verifications')->insertGetId(array_merge([
            'email' => 'holder+'.uniqid().'@example.test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $attributes));
    }

    private function assertListing(int $listingId, TicketResaleListingStatus $status, ?TicketResaleCloseReason $reason): void
    {
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => $status->name,
            'closed_reason' => $reason?->value,
        ]);
    }

    private function snapshot(): array
    {
        return [
            'listings' => DB::table('ticket_resale_listings')->orderBy('id')->get()->map(fn ($listing) => (array) $listing)->all(),
            'audit_entries' => DB::table('order_audit_logs')->count(),
            'verifications' => DB::table('self_service_verifications')->count(),
        ];
    }
}
