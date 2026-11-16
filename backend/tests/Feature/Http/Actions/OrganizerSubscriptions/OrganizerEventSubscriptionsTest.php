<?php

namespace Tests\Feature\Http\Actions\OrganizerSubscriptions;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Jobs\OrganizerSubscription\FanOutNewEventNotificationsJob;
use HiEvents\Jobs\OrganizerSubscription\SubscribeCompletedOrderJob;
use HiEvents\Mail\OrganizerSubscription\ConfirmOrganizerSubscriptionMail;
use HiEvents\Repository\Eloquent\OrganizerEventSubscriptionRepository;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountAnonymizationService;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerEventSubscriptionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $organizerId;

    private int $accountId;

    private array $headers;

    private OrganizerEventSubscriptionRepository $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Bus::fake();
        [$owner, $this->accountId] = $this->createAccountOwner();
        $this->headers = $this->headersFor($owner, $this->accountId);
        $this->organizerId = $this->createOrganizer($this->accountId);
        DB::table('organizers')->where('id', $this->organizerId)->update(['status' => 'LIVE']);
        DB::table('organizer_settings')->insert(['organizer_id' => $this->organizerId, 'new_event_notifications_enabled' => true]);
        $this->subscriptions = app(OrganizerEventSubscriptionRepository::class);
    }

    private function path(): string
    {
        return "/organizers/{$this->organizerId}/event-subscriptions";
    }

    private function subscriber(string $email = 'subscriber@example.com', array $attributes = []): int
    {
        return DB::table('organizer_event_subscriptions')->insertGetId(array_merge([
            'organizer_id' => $this->organizerId, 'email' => $email, 'source' => 'public', 'subscribed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function liveEvent(): int
    {
        $id = $this->createEvent($this->accountId, $this->organizerId);
        DB::table('events')->where('id', $id)->update(['status' => 'LIVE']);

        return $id;
    }

    public function test_public_signup_normalizes_deduplicates_and_throttles_confirmation_mail(): void
    {
        $path = '/public'.$this->path();
        $this->postJson($path, ['email' => 'Buyer@Example.com', 'locale' => 'de'])->assertNoContent();
        $this->postJson($path, ['email' => 'buyer@example.com', 'locale' => 'de'])->assertNoContent();
        $this->assertDatabaseHas('organizer_event_subscriptions', ['email' => 'buyer@example.com', 'locale' => 'de', 'confirmed_at' => null]);
        $this->assertSame(1, DB::table('organizer_event_subscriptions')->where('organizer_id', $this->organizerId)->count());
        Mail::assertQueued(ConfirmOrganizerSubscriptionMail::class, 1);
    }

    public function test_confirmation_requires_a_post_and_expires_after_24_hours(): void
    {
        $confirmation = $this->subscriptions->requestConfirmation($this->organizerId, 'confirm@example.com', 'en', 'public');
        $path = '/public'.$this->path().'/confirm/'.$confirmation->token;
        $this->getJson($path)->assertStatus(405);
        $this->assertDatabaseHas('organizer_event_subscriptions', ['id' => $confirmation->id, 'confirmed_at' => null]);
        $this->postJson($path)->assertNoContent();
        $this->assertNotNull(DB::table('organizer_event_subscriptions')->where('id', $confirmation->id)->value('confirmed_at'));
        $other = $this->subscriptions->requestConfirmation($this->organizerId, 'expired@example.com', 'en', 'public');
        $this->travel(25)->hours();
        $this->postJson('/public'.$this->path().'/confirm/'.$other->token)->assertNotFound();
    }

    public function test_confirmed_subscribers_do_not_get_more_confirmation_emails(): void
    {
        $this->subscriber(attributes: ['confirmed_at' => now()]);
        $this->postJson('/public'.$this->path(), ['email' => 'subscriber@example.com'])->assertNoContent();
        Mail::assertNothingQueued();
    }

    public function test_unsubscribe_invalidates_pending_links_and_is_idempotent(): void
    {
        $confirmation = $this->subscriptions->requestConfirmation($this->organizerId, 'pending@example.com', 'en', 'public');
        $token = app(SubscriptionTokens::class)->unsubscribeToken($confirmation->id);
        $path = '/public'.$this->path().'/unsubscribe/'.$token;
        $this->getJson($path)->assertStatus(405);
        $this->postJson($path, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
        $this->postJson($path)->assertNoContent();
        $this->postJson('/public'.$this->path().'/confirm/'.$confirmation->token)->assertNotFound();
        $this->assertDatabaseHas('organizer_event_subscriptions', ['id' => $confirmation->id, 'confirmation_token_hash' => null]);
    }

    public function test_resubscription_requires_fresh_confirmation(): void
    {
        $id = $this->subscriber(attributes: ['confirmed_at' => now()->subDay(), 'unsubscribed_at' => now()]);
        $confirmation = $this->subscriptions->requestConfirmation($this->organizerId, 'subscriber@example.com', 'en', 'public');
        $this->assertSame($id, $confirmation->id);
        $this->assertDatabaseHas('organizer_event_subscriptions', ['id' => $id, 'confirmed_at' => null, 'unsubscribed_at' => null]);
        $this->postJson('/public'.$this->path().'/confirm/'.$confirmation->token)->assertNoContent();
    }

    public function test_disabled_or_suspended_organizers_cannot_accept_signups(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update(['suspended_at' => now()]);
        $this->postJson('/public'.$this->path(), ['email' => 'buyer@example.com'])->assertNotFound();
        DB::table('accounts')->where('id', $this->accountId)->update(['suspended_at' => null]);
        app(OrganizerSettingsRepositoryInterface::class)->setNewEventNotificationsEnabled($this->organizerId, false);
        $this->postJson('/public'.$this->path(), ['email' => 'buyer@example.com'])->assertNotFound();
    }

    public function test_list_counts_filter_search_and_private_fields(): void
    {
        $this->subscriber('confirmed@example.com', ['confirmed_at' => now()]);
        $this->subscriber('pending@example.com');
        $this->subscriber('gone@example.com', ['unsubscribed_at' => now()]);
        $response = $this->getJson($this->path().'?status=confirmed&query=CONFIRMED', $this->headers)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.counts.confirmed', 1)
            ->assertJsonPath('meta.counts.pending', 1)->assertJsonPath('meta.counts.unsubscribed', 1);
        $this->assertArrayNotHasKey('confirmation_token_hash', $response->json('data.0'));
    }

    public function test_bulk_unsubscribe_rejects_foreign_ids_without_partial_updates(): void
    {
        $id = $this->subscriber();
        $otherOrganizerId = $this->createOrganizer($this->accountId);
        $otherId = $this->subscriber('other@example.com', ['organizer_id' => $otherOrganizerId]);
        $this->postJson($this->path().'/unsubscribe', ['ids' => [$id, $otherId]], $this->headers)->assertUnprocessable();
        $this->assertDatabaseHas('organizer_event_subscriptions', ['id' => $id, 'unsubscribed_at' => null]);
        $this->postJson($this->path().'/unsubscribe', ['ids' => [$id, $id]], $this->headers)->assertNoContent();
        $this->assertNotNull(DB::table('organizer_event_subscriptions')->where('id', $id)->value('unsubscribed_at'));
    }

    public function test_event_only_members_cannot_view_or_export_subscribers(): void
    {
        $eventId = $this->liveEvent();
        $member = $this->addRestrictedAccountUser($this->accountId, eventIds: [$eventId]);
        $headers = $this->headersFor($member, $this->accountId);
        $this->getJson($this->path(), $headers)->assertForbidden();
        $this->getJson($this->path().'/export', $headers)->assertForbidden();
    }

    public function test_read_only_organizer_member_can_view_export_but_not_unsubscribe(): void
    {
        $id = $this->subscriber();
        $member = $this->addRestrictedAccountUser($this->accountId, role: Role::VIEWER, organizerIds: [$this->organizerId]);
        $headers = $this->headersFor($member, $this->accountId);
        $this->getJson($this->path(), $headers)->assertOk();
        $this->getJson($this->path().'/export', $headers)->assertOk();
        $this->postJson($this->path().'/unsubscribe', ['ids' => [$id]], $headers)->assertForbidden();
    }

    public function test_csv_exports_all_filtered_rows_and_protects_formula_cells(): void
    {
        $this->subscriber('=formula@example.com', ['confirmed_at' => now()]);
        $this->subscriber('another@example.com', ['confirmed_at' => now()]);
        $this->subscriber('pending@example.com');
        $response = $this->getJson($this->path().'/export?status=confirmed&per_page=1', $this->headers)->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=formula@example.com", $content);
        $this->assertStringContainsString('another@example.com', $content);
        $this->assertStringNotContainsString('pending@example.com', $content);
    }

    public function test_first_publication_is_recorded_once_and_rollback_does_not_persist_it(): void
    {
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $first = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->travel(1)->hours();
        $this->subscriptions->recordPublication($eventId);
        $this->assertSame($first, DB::table('events')->where('id', $eventId)->value('first_published_at'));
        Bus::assertDispatchedTimes(FanOutNewEventNotificationsJob::class, 1);
        $other = $this->liveEvent();
        DB::beginTransaction();
        $this->subscriptions->recordPublication($other);
        DB::rollBack();
        $this->assertNull(DB::table('events')->where('id', $other)->value('first_published_at'));
    }

    public function test_publication_while_disabled_is_not_announced_after_reenable(): void
    {
        $settings = app(OrganizerSettingsRepositoryInterface::class);
        $settings->setNewEventNotificationsEnabled($this->organizerId, false);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $settings->setNewEventNotificationsEnabled($this->organizerId, true);
        $this->subscriptions->recordPublication($eventId);
        Bus::assertNotDispatched(FanOutNewEventNotificationsJob::class);
    }

    public function test_delivery_excludes_late_subscribers_and_is_deduplicated(): void
    {
        $id = $this->subscriber(attributes: ['confirmed_at' => now()->subMinute()]);
        $this->subscriber('late@example.com', ['confirmed_at' => now()->addMinute()]);
        $this->subscriber('pending@example.com');
        $this->subscriber('gone@example.com', ['confirmed_at' => now()->subMinute(), 'unsubscribed_at' => now()]);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $published = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->assertSame([$id], $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0));
        $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0);
        $calls = 0;
        $send = function () use (&$calls) {
            $calls++;
        };
        $this->subscriptions->deliver($eventId, $id, $send);
        $this->subscriptions->deliver($eventId, $id, $send);
        $this->assertSame(1, $calls);
    }

    public function test_disabling_invalidates_queued_deliveries_even_after_reenable(): void
    {
        $id = $this->subscriber(attributes: ['confirmed_at' => now()->subMinute()]);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $published = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0);
        $settings = app(OrganizerSettingsRepositoryInterface::class);
        $settings->setNewEventNotificationsEnabled($this->organizerId, false);
        $settings->setNewEventNotificationsEnabled($this->organizerId, true);
        $this->subscriptions->deliver($eventId, $id, fn () => $this->fail('Canceled delivery was sent'));
        $this->assertNotNull(DB::table('event_subscription_deliveries')->where('event_id', $eventId)->value('skipped_at'));
        $this->assertSame([], $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0));
    }

    public function test_unsubscribe_after_queueing_skips_delivery(): void
    {
        $id = $this->subscriber(attributes: ['confirmed_at' => now()->subMinute()]);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $published = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0);
        $this->subscriptions->unsubscribe($this->organizerId, [$id]);
        $this->subscriptions->deliver($eventId, $id, fn () => $this->fail('Unsubscribed recipient was emailed'));
        $this->assertNotNull(DB::table('event_subscription_deliveries')->where('event_id', $eventId)->value('skipped_at'));
    }

    public function test_completed_checkout_requests_confirmation_only_once_and_ignores_historical_orders(): void
    {
        $eventId = $this->liveEvent();
        $orderId = DB::table('orders')->insertGetId([
            'event_id' => $eventId, 'created_at' => now(), 'updated_at' => now(), 'short_id' => 'ord_'.uniqid(),
            'public_id' => 'public_'.uniqid(), 'status' => 'COMPLETED', 'currency' => 'USD',
            'first_name' => 'Buyer', 'last_name' => 'Person', 'email' => 'buyer@example.com',
            'opted_into_marketing_at' => now(), 'new_event_subscription_requested_at' => now(),
        ]);
        app()->call([new SubscribeCompletedOrderJob($orderId), 'handle']);
        $this->travel(2)->hours();
        app()->call([new SubscribeCompletedOrderJob($orderId), 'handle']);
        Mail::assertQueued(ConfirmOrganizerSubscriptionMail::class, 1);
        $this->assertDatabaseHas('organizer_event_subscriptions', ['email' => 'buyer@example.com', 'confirmed_at' => null, 'source' => 'checkout']);
        DB::table('orders')->where('id', $orderId)->update(['new_event_subscription_requested_at' => null, 'email' => 'historical@example.com']);
        app()->call([new SubscribeCompletedOrderJob($orderId), 'handle']);
        $this->assertDatabaseMissing('organizer_event_subscriptions', ['email' => 'historical@example.com']);
    }

    public function test_checkout_consent_availability_respects_event_setting_and_organizer_switch(): void
    {
        $eventId = $this->liveEvent();
        DB::table('event_settings')->insert(['event_id' => $eventId, 'show_marketing_opt_in' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($this->subscriptions->canSubscribeFromCheckout($eventId));
        DB::table('event_settings')->where('event_id', $eventId)->update(['show_marketing_opt_in' => false]);
        $this->assertFalse($this->subscriptions->canSubscribeFromCheckout($eventId));
    }

    public function test_subscribers_confirming_later_in_the_same_second_are_excluded(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00.100000'));
        $early = $this->subscriber('early@example.com', ['confirmed_at' => '2026-11-16 12:00:00.050000']);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $this->subscriber('late@example.com', ['confirmed_at' => '2026-11-16 12:00:00.900000']);
        $published = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->assertSame([$early], $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0));
    }

    public function test_unpublished_event_and_resubscribed_contact_are_not_sent_stale_announcements(): void
    {
        $id = $this->subscriber(attributes: ['confirmed_at' => now()->subMinute()]);
        $eventId = $this->liveEvent();
        $this->subscriptions->recordPublication($eventId);
        $published = DB::table('events')->where('id', $eventId)->value('first_published_at');
        $this->subscriptions->createDeliveryBatch($eventId, $this->organizerId, $published, 0, 0);
        DB::table('events')->where('id', $eventId)->update(['status' => 'DRAFT']);
        $this->subscriptions->deliver($eventId, $id, fn () => $this->fail('Draft event was announced'));
        $this->assertNotNull(DB::table('event_subscription_deliveries')->where('event_id', $eventId)->value('skipped_at'));
        $confirmation = $this->subscriptions->requestConfirmation($this->organizerId, 'fresh@example.com', 'en', 'public');
        $this->subscriptions->unsubscribe($this->organizerId, [$confirmation->id]);
        $new = $this->subscriptions->requestConfirmation($this->organizerId, 'fresh@example.com', 'en', 'public');
        $this->assertNotNull($new);
        $this->assertNotSame($confirmation->token, $new->token);
    }

    public function test_anonymizing_an_account_deletes_only_its_subscribers(): void
    {
        $id = $this->subscriber();
        app(AccountAnonymizationService::class)->anonymizeAccount($this->accountId);
        $this->assertDatabaseMissing('organizer_event_subscriptions', ['id' => $id]);
    }
}
