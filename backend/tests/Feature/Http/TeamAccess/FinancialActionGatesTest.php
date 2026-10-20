<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Jobs\Occurrence\BulkCancelOccurrencesJob;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class FinancialActionGatesTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    private int $occurrenceId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        [, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
        $this->occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(2),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_member_without_refunds_cannot_cancel_a_date_with_refunds(): void
    {
        $headers = $this->headers($this->organizerWithoutRefunds());

        $this->postJson($this->cancelPath(), ['refund_orders' => true], $headers)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->assertSame('ACTIVE', $this->occurrenceStatus());
        Queue::assertNotPushed(RefundOccurrenceOrdersJob::class);

        $this->postJson($this->cancelPath(), [], $headers)->assertOk();

        $this->assertSame('CANCELLED', $this->occurrenceStatus());
        Queue::assertNotPushed(RefundOccurrenceOrdersJob::class);
    }

    public function test_member_with_refunds_can_cancel_a_date_with_refunds(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->postJson($this->cancelPath(), ['refund_orders' => true], $this->headers($organizer))->assertOk();

        $this->assertSame('CANCELLED', $this->occurrenceStatus());
        Queue::assertPushed(RefundOccurrenceOrdersJob::class, 1);
    }

    public function test_member_without_refunds_cannot_bulk_cancel_dates_with_refunds(): void
    {
        $headers = $this->headers($this->organizerWithoutRefunds());

        $this->postJson($this->bulkPath(), $this->bulkCancel(refundOrders: true), $headers)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        Queue::assertNotPushed(BulkCancelOccurrencesJob::class);

        $this->postJson($this->bulkPath(), $this->bulkCancel(refundOrders: false), $headers)
            ->assertOk()
            ->assertJsonPath('updated_ids', [$this->occurrenceId]);

        Queue::assertPushed(BulkCancelOccurrencesJob::class, fn (BulkCancelOccurrencesJob $job) => $job->occurrenceIds === [$this->occurrenceId] && $job->refundOrders === false);
        Queue::assertPushed(BulkCancelOccurrencesJob::class, 1);
    }

    public function test_member_with_refunds_can_bulk_cancel_dates_with_refunds(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->postJson($this->bulkPath(), $this->bulkCancel(refundOrders: true), $this->headers($organizer))->assertOk();

        Queue::assertPushed(BulkCancelOccurrencesJob::class, fn (BulkCancelOccurrencesJob $job) => $job->refundOrders === true);
    }

    public function test_financial_report_exports_need_financials_but_check_in_exports_do_not(): void
    {
        $organizerWithoutFinancials = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($organizerWithoutFinancials, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        foreach ([$organizerWithoutFinancials, $viewer] as $member) {
            foreach (['revenue_summary', 'tax_summary', 'events_performance', 'platform_fees'] as $report) {
                $this->get($this->exportPath($report), $this->headers($member) + ['Accept' => 'application/json'])
                    ->assertForbidden()
                    ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
            }

            $this->get($this->exportPath('check_in_summary'), $this->headers($member))
                ->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        }

        $this->get($this->exportPath('revenue_summary'), $this->headers($this->addAccountUser($this->accountId, Role::ORGANIZER)))
            ->assertOk();
    }

    private function organizerWithoutRefunds(): User
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($user, $this->accountId, ['ISSUE_REFUNDS' => false]);

        return $user;
    }

    private function bulkCancel(bool $refundOrders): array
    {
        return [
            'action' => 'cancel',
            'occurrence_ids' => [$this->occurrenceId],
            'refund_orders' => $refundOrders,
        ];
    }

    private function occurrenceStatus(): string
    {
        return DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('status');
    }

    private function cancelPath(): string
    {
        return "/events/{$this->eventId}/occurrences/{$this->occurrenceId}/cancel";
    }

    private function bulkPath(): string
    {
        return "/events/{$this->eventId}/occurrences/bulk-update";
    }

    private function exportPath(string $report): string
    {
        return "/organizers/{$this->organizerId}/reports/{$report}/export";
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }
}
