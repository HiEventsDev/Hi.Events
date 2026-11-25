<?php

namespace Tests\Feature\Http\Actions\Attendees;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\Attendee\AttendeeDetailsChangedMail;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\VerifiesSelfService;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class AttendeeEmailChangeTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use VerifiesSelfService;

    private const ORIGINAL_EMAIL = 'ada@example.test';

    private const NEW_EMAIL = 'grace@example.test';

    private int $productId;

    private int $priceId;

    private int $occurrenceId;

    private string $checkInListShortId;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->checkInListShortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $this->checkInListShortId,
            'name' => 'Door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_buyer_changing_a_ticket_email_invalidates_the_previous_qr_code(): void
    {
        $attendee = $this->createAttendee();
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');

        $newShortId = $this->patchJson(
            "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}",
            ['email' => self::NEW_EMAIL],
            $this->selfServiceHeadersFor(self::ORIGINAL_EMAIL),
        )->assertOk()->json('new_short_id');

        $current = DB::table('attendees')->where('id', $attendee['id'])->first();

        $this->assertSame($newShortId, $current->short_id);
        $this->assertNotSame($attendee['public_id'], $current->public_id);
        $this->checkIn($attendee['public_id'])->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->checkIn($current->public_id)->assertOk();
    }

    public function test_a_buyer_changing_a_ticket_name_keeps_the_qr_code_and_tells_integrations(): void
    {
        $attendee = $this->createAttendee();
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');
        $updates = [];
        $this->app['events']->listen(AttendeeEvent::class, function (AttendeeEvent $event) use (&$updates): void {
            if ($event->type === DomainEventType::ATTENDEE_UPDATED) {
                $updates[] = $event->attendeeId;
            }
        });

        $this->patchJson(
            "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}",
            ['first_name' => 'Augusta'],
        )->assertOk();

        $this->assertSame([$attendee['id']], $updates);
        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'public_id' => $attendee['public_id'], 'short_id' => $attendee['short_id']]);
    }

    public function test_an_organizer_changing_a_ticket_email_invalidates_the_previous_qr_code(): void
    {
        $attendee = $this->createAttendee();

        $this->patchJson("/events/{$this->eventId}/attendees/{$attendee['id']}", [
            'email' => self::NEW_EMAIL,
        ], $this->organizerHeaders())->assertOk();

        $this->checkIn($attendee['public_id'])->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->checkIn(DB::table('attendees')->where('id', $attendee['id'])->value('public_id'))->assertOk();
    }

    public function test_an_organizer_changing_a_ticket_email_replaces_the_qr_code_and_emails_both_addresses(): void
    {
        $attendee = $this->createAttendee();
        Mail::fake();

        $this->putJson("/events/{$this->eventId}/attendees/{$attendee['id']}", [
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'email' => self::NEW_EMAIL,
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
        ], $this->organizerHeaders())->assertOk();

        $current = DB::table('attendees')->where('id', $attendee['id'])->first();

        $this->assertNotSame($attendee['public_id'], $current->public_id);
        $this->assertNotSame($attendee['short_id'], $current->short_id);
        Mail::assertQueued(AttendeeTicketMail::class, fn ($mail) => $mail->hasTo(self::NEW_EMAIL));
        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn ($mail) => $mail->hasTo(self::ORIGINAL_EMAIL));
        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $attendee['id'],
            'action' => OrderAuditAction::ATTENDEE_UPDATED_BY_ORGANIZER->value,
            'changed_fields' => 'email,credentials_replaced',
        ]);
    }

    public function test_an_organizer_quick_edit_of_the_email_replaces_the_qr_code_too(): void
    {
        $attendee = $this->createAttendee();
        Mail::fake();

        $this->patchJson("/events/{$this->eventId}/attendees/{$attendee['id']}", [
            'email' => self::NEW_EMAIL,
        ], $this->organizerHeaders())->assertOk();

        $this->assertDatabaseMissing('attendees', ['id' => $attendee['id'], 'public_id' => $attendee['public_id']]);
        Mail::assertQueued(AttendeeTicketMail::class, fn ($mail) => $mail->hasTo(self::NEW_EMAIL));
        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn ($mail) => $mail->hasTo(self::ORIGINAL_EMAIL));
    }

    public function test_an_organizer_edit_that_keeps_the_email_keeps_the_qr_code(): void
    {
        $attendee = $this->createAttendee();
        Mail::fake();

        $this->patchJson("/events/{$this->eventId}/attendees/{$attendee['id']}", [
            'first_name' => 'Augusta',
        ], $this->organizerHeaders())->assertOk();

        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'public_id' => $attendee['public_id'], 'short_id' => $attendee['short_id']]);
        Mail::assertNotQueued(AttendeeDetailsChangedMail::class);
        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $attendee['id'],
            'action' => OrderAuditAction::ATTENDEE_UPDATED_BY_ORGANIZER->value,
            'changed_fields' => 'first_name',
        ]);
    }

    public function test_ticket_history_shows_the_change_without_ticket_links_or_qr_values(): void
    {
        $attendee = $this->createAttendee();
        DB::table('order_audit_logs')->insert([
            'event_id' => $this->eventId,
            'order_id' => $attendee['order_id'],
            'attendee_id' => $attendee['id'],
            'action' => OrderAuditAction::ATTENDEE_UPDATED->value,
            'old_values' => json_encode(['email' => 'first@example.test', 'short_id' => 'a_legacyold']),
            'new_values' => json_encode(['email' => self::ORIGINAL_EMAIL, 'short_id' => 'a_legacynew']),
            'changed_fields' => 'email,short_id',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Legacy Browser',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $this->patchJson("/events/{$this->eventId}/attendees/{$attendee['id']}", ['email' => self::NEW_EMAIL], $this->organizerHeaders())->assertOk();

        $attendeeHistory = $this->getJson("/events/{$this->eventId}/attendees/{$attendee['id']}/history", $this->organizerHeaders())->assertOk();
        $orderHistory = $this->getJson("/events/{$this->eventId}/orders/{$attendee['order_id']}/history", $this->organizerHeaders())->assertOk();

        $attendeeHistory->assertJsonPath('data.0.action', OrderAuditAction::ATTENDEE_UPDATED_BY_ORGANIZER->value);
        $attendeeHistory->assertJsonPath('data.0.old_values.email', self::ORIGINAL_EMAIL);
        $attendeeHistory->assertJsonPath('data.0.new_values.email', self::NEW_EMAIL);
        $attendeeHistory->assertJsonPath('data.0.new_values.credentials_replaced', true);
        $attendeeHistory->assertJsonPath('data.1.new_values', ['email' => self::ORIGINAL_EMAIL]);
        $this->assertCount(2, $orderHistory->json('data'));

        foreach ([$attendeeHistory->getContent(), $orderHistory->getContent()] as $body) {
            $this->assertStringNotContainsString('a_legacy', $body);
            $this->assertStringNotContainsString('203.0.113.9', $body);
            $this->assertStringNotContainsString('Legacy Browser', $body);
            $this->assertStringNotContainsString(DB::table('attendees')->where('id', $attendee['id'])->value('short_id'), $body);
            $this->assertStringNotContainsString(DB::table('attendees')->where('id', $attendee['id'])->value('public_id'), $body);
        }
    }

    public function test_ticket_history_is_closed_to_other_accounts_and_scoped_to_the_event(): void
    {
        $attendee = $this->createAttendee();
        [$outsider, $outsiderAccountId] = $this->createAccountOwner();
        $otherEventId = $this->insertEvent(EventType::SINGLE->name);

        $this->getJson("/events/{$this->eventId}/attendees/{$attendee['id']}/history", $this->headersFor($outsider, $outsiderAccountId))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->getJson("/events/{$this->eventId}/orders/{$attendee['order_id']}/history", $this->headersFor($outsider, $outsiderAccountId))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->getJson("/events/$otherEventId/attendees/{$attendee['id']}/history", $this->organizerHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
        $this->getJson("/events/$otherEventId/orders/{$attendee['order_id']}/history", $this->organizerHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    private function createAttendee(): array
    {
        return $this->postJson("/events/{$this->eventId}/attendees", [
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'email' => self::ORIGINAL_EMAIL,
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'amount_paid' => 0,
            'send_confirmation_email' => false,
            'locale' => 'en',
        ], $this->organizerHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->json('data');
    }

    private function organizerHeaders(): array
    {
        return $this->headersFor(User::find($this->userId), $this->accountId);
    }

    private function checkIn(string $publicId)
    {
        return $this->postJson("/public/check-in-lists/{$this->checkInListShortId}/check-ins", [
            'attendees' => [['public_id' => $publicId, 'action' => 'check-in']],
        ]);
    }
}
