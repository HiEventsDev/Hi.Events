<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class OrderEventScopeTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const SESSION = 'scope-session-identifier';

    private int $otherEventId;

    private string $orderShortId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        $this->otherEventId = $this->insertEvent();

        foreach ([$this->eventId, $this->otherEventId] as $eventId) {
            DB::table('event_settings')->insert([
                'event_id' => $eventId,
                'payment_providers' => json_encode(['OFFLINE']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, null);
        $orderId = $this->reserve($productId, $priceId, $occurrenceId, 1);

        DB::table('orders')->where('id', $orderId)->update(['session_id' => self::SESSION]);
        $this->orderShortId = DB::table('orders')->where('id', $orderId)->value('short_id');
    }

    public function test_the_order_is_returned_under_its_own_event(): void
    {
        $this->getJson($this->url($this->eventId))->assertOk();
    }

    #[DataProvider('orderRoutes')]
    public function test_the_order_is_not_found_under_another_event(string $method, string $suffix, array $body): void
    {
        $this->json($method, $this->url($this->otherEventId, $suffix), $body)->assertNotFound();
    }

    public static function orderRoutes(): array
    {
        return [
            'view' => ['GET', '', []],
            'complete' => ['PUT', '', ['order' => ['first_name' => 'Ana', 'last_name' => 'Buyer', 'email' => 'ana@example.test']]],
            'abandon' => ['POST', '/abandon', []],
            'await offline payment' => ['POST', '/await-offline-payment', []],
            'payment link' => ['POST', '/payment-link', []],
            'report offline payment' => ['POST', '/report-offline-payment', []],
            'invoice' => ['GET', '/invoice', []],
            'create payment intent' => ['POST', '/stripe/payment_intent', []],
            'get payment intent' => ['GET', '/stripe/payment_intent', []],
            'self-service edit' => ['PATCH', '', ['first_name' => 'Ana']],
            'resend confirmation' => ['POST', '/resend-confirmation', []],
        ];
    }

    private function url(int $eventId, string $suffix = ''): string
    {
        return "/public/events/$eventId/order/{$this->orderShortId}$suffix?session_identifier=".self::SESSION;
    }
}
