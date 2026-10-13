<?php

namespace Tests\Feature\Http\Actions\Orders;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class UnpublishedEventOrderAccessTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private string $ownerToken;

    private string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $outsiderUserId = $this->userId;
        $outsiderAccountId = $this->accountId;

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->ownerToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
        $this->outsiderToken = JWTAuth::claims(['account_id' => $outsiderAccountId])->fromUser(User::find($outsiderUserId));
    }

    public function test_the_events_own_account_can_test_checkout_on_a_draft_event(): void
    {
        $this->setEventStatus(EventStatus::DRAFT);

        $this->createOrder($this->ownerToken)->assertCreated();
    }

    public function test_another_account_cannot_order_on_a_draft_event(): void
    {
        $this->setEventStatus(EventStatus::DRAFT);

        $this->createOrder($this->outsiderToken)->assertForbidden();
    }

    public function test_anonymous_buyers_cannot_order_on_a_draft_event(): void
    {
        $this->setEventStatus(EventStatus::DRAFT);

        $this->createOrder(null)->assertForbidden();
    }

    public function test_nobody_can_order_on_an_event_pending_review(): void
    {
        $this->setEventStatus(EventStatus::PENDING_MANUAL_REVIEW);

        $this->createOrder($this->ownerToken)->assertForbidden();
    }

    private function setEventStatus(EventStatus $status): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => $status->name]);
    }

    private function createOrder(?string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return $this->postJson("/public/events/{$this->eventId}/order", [
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [[
                    'price_id' => $this->priceId,
                    'quantity' => 1,
                ]],
            ]],
        ], $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
    }
}
