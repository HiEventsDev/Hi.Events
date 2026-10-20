<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class CrossEventScopingTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerA;

    private int $organizerB;

    private int $eventA;

    private int $eventB;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => true]);
        $this->organizerA = $this->createOrganizer($this->accountId, 'A');
        $this->organizerB = $this->createOrganizer($this->accountId, 'B');
        $this->eventA = $this->createEvent($this->accountId, $this->organizerA, 'Event A');
        $this->eventB = $this->createEvent($this->accountId, $this->organizerB, 'Event B');
    }

    public function test_an_event_template_from_another_event_cannot_be_edited_or_deleted_through_this_event(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $templateId = $this->template(eventId: $this->eventB);

        $this->putJson("/events/{$this->eventA}/email-templates/{$templateId}", [
            'subject' => 'Hijacked',
            'body' => 'Click here',
            'ctaLabel' => 'Go',
        ], $this->headers($user))->assertNotFound();

        $this->deleteJson("/events/{$this->eventA}/email-templates/{$templateId}", [], $this->headers($user))->assertNotFound();

        $this->assertSame('Original', DB::table('email_templates')->where('id', $templateId)->value('subject'));
        $this->assertNull(DB::table('email_templates')->where('id', $templateId)->value('deleted_at'));
    }

    public function test_an_organizer_template_from_another_organizer_cannot_be_edited_or_deleted(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerA]);
        $templateId = $this->template(organizerId: $this->organizerB);

        $this->putJson("/organizers/{$this->organizerA}/email-templates/{$templateId}", [
            'subject' => 'Hijacked',
            'body' => 'Click here',
            'ctaLabel' => 'Go',
        ], $this->headers($user))->assertNotFound();

        $this->deleteJson("/organizers/{$this->organizerA}/email-templates/{$templateId}", [], $this->headers($user))->assertNotFound();

        $this->assertSame('Original', DB::table('email_templates')->where('id', $templateId)->value('subject'));
        $this->assertNull(DB::table('email_templates')->where('id', $templateId)->value('deleted_at'));
    }

    public function test_deleting_a_product_through_another_event_leaves_it_untouched(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $productId = DB::table('products')->insertGetId([
            'title' => 'Event B ticket',
            'event_id' => $this->eventB,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_prices')->insert([
            'product_id' => $productId,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/events/{$this->eventA}/products/{$productId}", [], $this->headers($user))->assertNotFound();

        $this->assertSame(1, DB::table('product_prices')->where('product_id', $productId)->whereNull('deleted_at')->count());
    }

    public function test_a_product_edit_cannot_take_over_a_price_from_another_event(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $ownProduct = $this->product($this->eventA, 'Event A ticket');
        $ownPrice = $this->price($ownProduct, 'Standard');
        $victimPrice = $this->price($this->product($this->eventB, 'Event B ticket'), 'VIP');

        $this->putJson("/events/{$this->eventA}/products/{$ownProduct}", [
            'title' => 'Event A ticket',
            'type' => 'TIERED',
            'product_type' => 'TICKET',
            'product_category_id' => DB::table('products')->where('id', $ownProduct)->value('product_category_id'),
            'prices' => [
                ['id' => $ownPrice, 'price' => 10, 'label' => 'Standard'],
                ['id' => $victimPrice, 'price' => 0, 'label' => 'Free', 'initial_quantity_available' => 999999],
            ],
        ], $this->headers($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prices.1.id');

        $victim = DB::table('product_prices')->where('id', $victimPrice)->first();
        $this->assertSame('VIP', $victim->label);
        $this->assertNotSame($ownProduct, $victim->product_id);
    }

    public function test_editing_an_order_through_another_event_changes_nothing_and_sends_no_webhook(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventB,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Queue::fake();

        $this->putJson("/events/{$this->eventA}/orders/{$orderId}", [
            'first_name' => 'Mallory',
            'last_name' => 'Buyer',
            'email' => 'mallory@example.test',
        ], $this->headers($user))->assertNotFound();

        $this->assertSame('ada@example.test', DB::table('orders')->where('id', $orderId)->value('email'));
        Queue::assertNothingPushed();
    }

    public function test_editing_a_product_through_another_event_is_not_found(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $foreignProduct = $this->product($this->eventB, 'Event B ticket');
        $ownProduct = $this->product($this->eventA, 'Event A ticket');

        $this->putJson("/events/{$this->eventA}/products/{$foreignProduct}", [
            'title' => 'Hijacked',
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'product_category_id' => DB::table('products')->where('id', $ownProduct)->value('product_category_id'),
            'prices' => [['price' => 0]],
        ], $this->headers($user))->assertNotFound();

        $this->assertSame('Event B ticket', DB::table('products')->where('id', $foreignProduct)->value('title'));
    }

    public function test_manual_attendees_cannot_use_another_accounts_taxes(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $foreignTaxId = DB::table('taxes_and_fees')->insertGetId([
            'account_id' => $otherAccountId,
            'name' => 'Other account tax',
            'calculation_type' => 'PERCENTAGE',
            'type' => 'TAX',
            'rate' => 17.5,
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $productId = $this->product($this->eventA, 'Event A ticket');
        $priceId = $this->price($productId, 'Standard');
        DB::table('event_occurrences')->insert([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $this->eventA,
            'start_date' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/events/{$this->eventA}/attendees", [
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'email' => 'guest@example.test',
            'first_name' => 'Guest',
            'last_name' => 'Person',
            'amount_paid' => 10,
            'send_confirmation_email' => false,
            'locale' => 'en',
            'taxes_and_fees' => [['tax_or_fee_id' => $foreignTaxId, 'amount' => 1]],
        ], $this->headers($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('taxes_and_fees');

        $this->assertSame(0, DB::table('order_items')->where('taxes_and_fees_rollup', 'like', '%Other account tax%')->count());
    }

    public function test_waitlist_offers_only_accept_prices_from_this_event(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $productId = DB::table('products')->insertGetId([
            'title' => 'Event B ticket',
            'event_id' => $this->eventB,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/events/{$this->eventA}/waitlist/offer-next", ['product_price_id' => $priceId], $this->headers($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_price_id');
    }

    public function test_restricted_members_get_an_access_error_rather_than_a_logout_on_account_taxes(): void
    {
        $user = $this->addRestrictedAccountUser($this->accountId, Role::ORGANIZER, eventIds: [$this->eventA]);
        $taxId = DB::table('taxes_and_fees')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'VAT',
            'calculation_type' => 'PERCENTAGE',
            'type' => 'TAX',
            'rate' => 20,
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/accounts/{$this->accountId}/taxes-and-fees/{$taxId}", [], $this->headers($user))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCESS_RESTRICTED');
    }

    private function product(int $eventId, string $title): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Tickets',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'title' => $title,
            'event_id' => $eventId,
            'product_category_id' => $categoryId,
            'type' => 'TIERED',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function price(int $productId, string $label): int
    {
        return DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'label' => $label,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function template(?int $eventId = null, ?int $organizerId = null): int
    {
        return DB::table('email_templates')->insertGetId([
            'account_id' => $this->accountId,
            'event_id' => $eventId,
            'organizer_id' => $organizerId,
            'template_type' => 'order_confirmation',
            'subject' => 'Original',
            'body' => 'Body',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }
}
