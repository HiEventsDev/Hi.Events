<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Organizer\OfflinePaymentReportedMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class ReportOfflinePaymentTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $orderId;

    private string $orderShortId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'payment_providers' => json_encode(['OFFLINE']),
            'offline_payment_instructions' => '<p>Pay by bank transfer</p>',
            'notify_organizer_of_new_orders' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, null);

        $this->orderId = $this->sellTickets($productId, $priceId, $occurrenceId, 1, OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
        DB::table('orders')->where('id', $this->orderId)->update([
            'first_name' => 'Ray',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.test',
            'total_gross' => 10,
        ]);
        $this->orderShortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');
    }

    public function test_the_buyer_can_tell_the_organizer_they_have_paid(): void
    {
        $this->postJson($this->url(), ['note' => 'INV-2027/042'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::AWAITING_OFFLINE_PAYMENT->name);

        $order = DB::table('orders')->where('id', $this->orderId)->first();
        $this->assertNotNull($order->offline_payment_reported_at);
        $this->assertSame('INV-2027/042', $order->offline_payment_note);
        $this->assertSame(OrderStatus::AWAITING_OFFLINE_PAYMENT->name, $order->status);

        $organizerEmail = DB::table('organizers')->where('id', $this->organizerId)->value('email');
        Mail::assertQueued(OfflinePaymentReportedMail::class, fn (OfflinePaymentReportedMail $mail) => $mail->hasTo($organizerEmail));
    }

    public function test_the_organizer_email_shows_the_order_and_escapes_the_note(): void
    {
        $this->postJson($this->url(), ['note' => "O'Brien & Co 42"])->assertOk();

        Mail::assertQueued(OfflinePaymentReportedMail::class, function (OfflinePaymentReportedMail $mail) {
            $html = $mail->render();

            $this->assertStringContainsString("O'Brien &amp; Co 42", $html);
            $this->assertStringContainsString('Note from the buyer', $html);
            $this->assertStringContainsString(DB::table('orders')->where('id', $this->orderId)->value('public_id'), $html);
            $this->assertStringContainsString('Ray Buyer', $html);

            return true;
        });
    }

    public function test_everyday_note_formats_are_accepted(): void
    {
        $this->postJson($this->url(), ['note' => 'TRF_2027-042 #7 / Smith & Co!'])->assertOk();
    }

    public function test_the_note_is_optional(): void
    {
        $this->postJson($this->url())->assertOk();

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('offline_payment_note'));
        $this->assertNotNull(DB::table('orders')->where('id', $this->orderId)->value('offline_payment_reported_at'));
    }

    public function test_the_organizer_is_not_emailed_when_new_order_notifications_are_off(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['notify_organizer_of_new_orders' => false]);

        $this->postJson($this->url())->assertOk();

        Mail::assertNotQueued(OfflinePaymentReportedMail::class);
    }

    public function test_a_payment_can_only_be_reported_once(): void
    {
        $this->postJson($this->url(), ['note' => 'first'])->assertOk();
        $this->postJson($this->url(), ['note' => 'second'])->assertStatus(409);

        $this->assertSame('first', DB::table('orders')->where('id', $this->orderId)->value('offline_payment_note'));
        Mail::assertQueued(OfflinePaymentReportedMail::class, 1);
    }

    public function test_only_orders_awaiting_offline_payment_can_be_reported(): void
    {
        foreach ([OrderStatus::COMPLETED, OrderStatus::RESERVED, OrderStatus::CANCELLED] as $status) {
            DB::table('orders')->where('id', $this->orderId)->update(['status' => $status->name]);
            $this->postJson($this->url())->assertStatus(409);
        }

        Mail::assertNothingQueued();
    }

    public function test_reporting_can_be_turned_off(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['offline_payment_reporting_enabled' => false]);

        $this->postJson($this->url())->assertStatus(409);
    }

    public function test_the_order_must_belong_to_the_event_in_the_url(): void
    {
        $otherEventId = $this->insertEvent();

        $this->postJson("/public/events/{$otherEventId}/order/{$this->orderShortId}/report-offline-payment")
            ->assertNotFound();
    }

    #[DataProvider('unsafeNotes')]
    public function test_notes_that_could_render_as_links_or_markup_are_rejected(string $note): void
    {
        $this->postJson($this->url(), ['note' => $note])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('offline_payment_reported_at'));
    }

    public static function unsafeNotes(): iterable
    {
        yield 'markdown link' => ['[pay here](https://evil.test)'];
        yield 'html' => ['<b>paid</b>'];
        yield 'bare url' => ['https://evil.test'];
        yield 'www host' => ['www.evil.test'];
        yield 'bare domain' => ['pay at evil-bank.com instead'];
        yield 'image' => ['![x](https://evil.test/x.png)'];
        yield 'autolink' => ['<mailto:a@evil.test>'];
        yield 'too long' => [str_repeat('a', 101)];
    }

    private function url(): string
    {
        return "/public/events/{$this->eventId}/order/{$this->orderShortId}/report-offline-payment";
    }
}
