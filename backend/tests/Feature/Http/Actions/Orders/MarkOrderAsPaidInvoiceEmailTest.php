<?php

namespace Tests\Feature\Http\Actions\Orders;

use Barryvdh\DomPDF\Facade\Pdf;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Order\InvoicePdfRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class MarkOrderAsPaidInvoiceEmailTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $orderId;

    private int $invoiceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, null);

        $this->orderId = $this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
        DB::table('orders')->where('id', $this->orderId)->update([
            'email' => 'buyer@example.test',
            'first_name' => 'Robin',
            'last_name' => 'Buyer',
            'total_gross' => 10,
            'total_before_additions' => 10,
            'payment_status' => 'AWAITING_OFFLINE_PAYMENT',
        ]);
        $this->insertOrderItem($this->orderId, $productId, $priceId, $occurrenceId, 1);
        $this->insertAttendee($this->orderId, $productId, $priceId, $occurrenceId, AttendeeStatus::AWAITING_PAYMENT->name);

        $this->invoiceId = DB::table('invoices')->insertGetId([
            'order_id' => $this->orderId,
            'account_id' => $this->accountId,
            'invoice_number' => 'INV-0001',
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'total_amount' => 10,
            'status' => InvoiceStatus::UNPAID->name,
            'items' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_invoice_attached_to_the_marked_as_paid_email_is_the_paid_invoice(): void
    {
        $renderedInvoiceStatuses = [];
        $renderer = Mockery::mock(InvoicePdfRenderer::class);
        $renderer->shouldReceive('render')
            ->andReturnUsing(function (...$arguments) use (&$renderedInvoiceStatuses) {
                /** @var InvoiceDomainObject $invoice */
                $invoice = $arguments[4];
                $renderedInvoiceStatuses[] = [$invoice->getId(), $invoice->getStatus()];

                return Pdf::loadHTML('invoice');
            });
        $this->app->instance(InvoicePdfRenderer::class, $renderer);

        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/mark-as-paid", [], $this->authHeaders())
            ->assertOk();

        $this->assertSame(InvoiceStatus::PAID->name, DB::table('invoices')->where('id', $this->invoiceId)->value('status'));
        $this->assertSame([[$this->invoiceId, InvoiceStatus::PAID->name]], $renderedInvoiceStatuses);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))];
    }
}
