<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\CarbonInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignRevenueTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private CarbonInterface $sentAt;

    private int $campaignId;

    private int $promoCodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
        $this->sentAt = now()->subDays(10)->startOfSecond();
        $this->promoCodeId = DB::table('promo_codes')->insertGetId([
            'event_id' => $this->eventId,
            'code' => 'early-campaign',
            'discount' => 0,
            'discount_type' => 'NONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->campaignId = $this->insertCampaign([
            'status' => 'SENT',
            'sent_at' => $this->sentAt,
            'promo_code_id' => $this->promoCodeId,
            'attribution_window_days' => 7,
        ]);
    }

    public function test_orders_are_attributed_by_code_or_click_and_influenced_when_only_received(): void
    {
        $this->recipient('clicker@example.com', clickedAfterHours: 2);
        $this->recipient('reader@example.com', clickedAfterHours: null);
        $this->recipient('late.clicker@example.com', clickedAfterHours: 72);
        $this->recipient('skipped@example.com', clickedAfterHours: null, status: 'SKIPPED');
        $this->recipient('euro.clicker@example.com', clickedAfterHours: 1);

        $this->order('clicker@example.com', 100, hoursAfterSend: 5);
        $this->order('clicker@example.com', 40, hoursAfterSend: 1);
        $this->order('clicker@example.com', 999, hoursAfterSend: 24 * 7 + 1);
        $this->order('clicker@example.com', 999, hoursAfterSend: -3);
        $this->order('clicker@example.com', 999, hoursAfterSend: 6, attributes: ['status' => 'CANCELLED']);
        $this->order('code.user@example.com', 60, hoursAfterSend: 24, attributes: ['promo_code_id' => $this->promoCodeId]);
        $this->order('code.user@example.com', 999, hoursAfterSend: 24 * 8, attributes: ['promo_code_id' => $this->promoCodeId]);
        $this->order('reader@example.com', 30, hoursAfterSend: 24);
        $this->order('late.clicker@example.com', 20, hoursAfterSend: 48);
        $this->order('skipped@example.com', 999, hoursAfterSend: 24);
        $this->order('stranger@example.com', 999, hoursAfterSend: 24);
        $this->order('euro.clicker@example.com', 75, hoursAfterSend: 3, attributes: ['currency' => 'EUR']);

        $this->assertEquals(
            [
                ['currency' => 'USD', 'attributed_orders' => 2, 'attributed_revenue' => 160.0, 'influenced_orders' => 3, 'influenced_revenue' => 90.0],
                ['currency' => 'EUR', 'attributed_orders' => 1, 'attributed_revenue' => 75.0, 'influenced_orders' => 0, 'influenced_revenue' => 0.0],
            ],
            $this->revenue(),
        );
    }

    public function test_orders_by_recipients_who_did_not_click_are_influenced_when_the_campaign_has_no_code(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['promo_code_id' => null]);
        $this->recipient('clicker@example.com', clickedAfterHours: 2);
        $this->recipient('reader@example.com', clickedAfterHours: null);
        $this->order('clicker@example.com', 100, hoursAfterSend: 5);
        $this->order('clicker@example.com', 40, hoursAfterSend: 1);
        $this->order('reader@example.com', 30, hoursAfterSend: 24);

        $this->assertEquals(
            [['currency' => 'USD', 'attributed_orders' => 1, 'attributed_revenue' => 100.0, 'influenced_orders' => 2, 'influenced_revenue' => 70.0]],
            $this->revenue(),
        );
    }

    public function test_orders_by_recipients_who_did_not_use_the_code_are_influenced_when_the_campaign_has_a_code(): void
    {
        $this->recipient('reader@example.com', clickedAfterHours: null);
        $this->recipient('code.reader@example.com', clickedAfterHours: null);
        $this->order('reader@example.com', 30, hoursAfterSend: 24);
        $this->order('code.reader@example.com', 45, hoursAfterSend: 24, attributes: ['promo_code_id' => $this->promoCodeId]);

        $this->assertEquals(
            [['currency' => 'USD', 'attributed_orders' => 1, 'attributed_revenue' => 45.0, 'influenced_orders' => 1, 'influenced_revenue' => 30.0]],
            $this->revenue(),
        );
    }

    public function test_attributed_and_influenced_revenue_are_net_of_refunds_on_the_same_order(): void
    {
        $this->recipient('clicker@example.com', clickedAfterHours: 2);
        $this->recipient('reader@example.com', clickedAfterHours: null);
        $this->order('clicker@example.com', 100, hoursAfterSend: 5, attributes: ['total_refunded' => 40]);
        $this->order('clicker@example.com', 60, hoursAfterSend: 6);
        $this->order('reader@example.com', 30, hoursAfterSend: 24, attributes: ['total_refunded' => 30]);
        $this->order('reader@example.com', 20, hoursAfterSend: 25);

        $this->assertSame(2, DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->where('type', 'ORDER_REFUNDED')->count());
        $this->assertEquals(
            [['currency' => 'USD', 'attributed_orders' => 2, 'attributed_revenue' => 120.0, 'influenced_orders' => 2, 'influenced_revenue' => 20.0]],
            $this->revenue(),
        );
    }

    public function test_a_click_on_a_different_campaign_does_not_attribute_the_order(): void
    {
        $otherCampaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => $this->sentAt]);
        $contactId = $this->insertSubscribedContact('ada@example.com');
        $this->insertRecipient($otherCampaignId, $contactId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'other-ada', 'sent_at' => $this->sentAt]);
        $this->insertDelivery('other-ada', ['first_clicked_at' => $this->sentAt->copy()->addHour()]);
        $this->order('ada@example.com', 50, hoursAfterSend: 5);

        $this->assertSame([], $this->revenue());
        $this->assertEquals(
            [['currency' => 'USD', 'attributed_orders' => 1, 'attributed_revenue' => 50.0, 'influenced_orders' => 0, 'influenced_revenue' => 0.0]],
            $this->revenue($otherCampaignId),
        );
    }

    public function test_a_campaign_without_a_code_does_not_attribute_orders_that_used_no_code(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['promo_code_id' => null]);
        $this->order('stranger@example.com', 50, hoursAfterSend: 5);
        $this->order('code.user@example.com', 60, hoursAfterSend: 5, attributes: ['promo_code_id' => $this->promoCodeId]);

        $this->assertSame([], $this->revenue());
    }

    public function test_orders_of_another_organizer_are_never_counted(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');
        $orderId = $this->insertOrder(['event_id' => $otherEventId, 'email' => 'code.user@example.com', 'promo_code_id' => $this->promoCodeId, 'created_at' => $this->sentAt->copy()->addHour()]);
        $this->trackOrganizer($this->accountId, $otherOrganizerId);
        app(ContactSyncService::class)->projectOrders($this->accountId, $otherOrganizerId, [$orderId]);

        $this->assertSame(1, DB::table('crm_contact_activities')->where('organizer_id', $otherOrganizerId)->where('type', 'ORDER_COMPLETED')->count());
        $this->assertSame([], $this->revenue());
    }

    public function test_an_order_placed_before_the_recipients_own_email_went_out_is_not_influenced(): void
    {
        $this->recipient('slow.batch@example.com', clickedAfterHours: null, sentAfterHours: 10);

        $this->order('slow.batch@example.com', 999, hoursAfterSend: 5);
        $this->order('slow.batch@example.com', 30, hoursAfterSend: 12);

        $this->assertEquals(
            [['currency' => 'USD', 'attributed_orders' => 0, 'attributed_revenue' => 0.0, 'influenced_orders' => 1, 'influenced_revenue' => 30.0]],
            $this->revenue(),
        );
    }

    public function test_a_campaign_that_has_not_been_sent_has_no_revenue(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['sent_at' => null, 'status' => 'DRAFT']);
        $this->order('code.user@example.com', 60, hoursAfterSend: 5, attributes: ['promo_code_id' => $this->promoCodeId]);

        $this->assertSame([], $this->revenue());
    }

    private function recipient(string $email, ?int $clickedAfterHours, string $status = 'SENT', int $sentAfterHours = 0): void
    {
        $contactId = $this->insertSubscribedContact($email);
        $this->insertRecipient($this->campaignId, $contactId, $email, [
            'status' => $status,
            'provider_message_id' => "message-$email",
            'sent_at' => $status === 'SENT' ? $this->sentAt->copy()->addHours($sentAfterHours) : null,
        ]);
        $this->insertDelivery("message-$email", [
            'delivered_at' => $this->sentAt,
            'first_clicked_at' => $clickedAfterHours === null ? null : $this->sentAt->copy()->addHours($clickedAfterHours),
        ]);
    }

    private function order(string $email, float $amount, int $hoursAfterSend, array $attributes = []): void
    {
        $placedAt = $this->sentAt->copy()->addHours($hoursAfterSend);

        $this->projectOrders($this->insertOrder([
            'email' => $email,
            'total_gross' => $amount,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            ...$attributes,
        ]));
    }

    private function revenue(?int $campaignId = null): array
    {
        $repository = app(CrmCampaignRepositoryInterface::class);

        return $repository->getAttributedRevenue($repository->findById($campaignId ?? $this->campaignId))
            ->map(static fn (object $row) => [
                'currency' => $row->currency,
                'attributed_orders' => (int) $row->attributed_orders,
                'attributed_revenue' => (float) $row->attributed_revenue,
                'influenced_orders' => (int) $row->influenced_orders,
                'influenced_revenue' => (float) $row->influenced_revenue,
            ])
            ->all();
    }
}
