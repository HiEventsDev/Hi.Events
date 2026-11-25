<?php

namespace Tests\Feature\Http\Actions\TicketTransfer;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketTransferBlock;
use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\Http\Actions\TicketTransfer\BaseTicketTransferPublicAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use HiEvents\Mail\TicketTransfer\TicketTransferInvitationMail;
use HiEvents\Mail\TicketTransfer\TicketTransferNoticeMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\VerifiesSelfService;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class TicketTransferTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use VerifiesSelfService;

    private const BUYER_EMAIL = 'buyer@example.test';

    private const GUEST_EMAIL = 'guest@example.test';

    private const RECIPIENT_EMAIL = 'recipient@example.test';

    private array $attendee;

    private string $orderShortId;

    private int $productId;

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
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);

        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->attendee = $this->postJson("/events/{$this->eventId}/attendees", [
            'product_id' => $this->productId,
            'product_price_id' => $this->insertPrice($this->productId, null),
            'event_occurrence_id' => $this->insertOccurrence(daysAhead: 30),
            'email' => self::GUEST_EMAIL,
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'amount_paid' => 0,
            'send_confirmation_email' => false,
            'locale' => 'en',
        ], $this->organizerHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->json('data');

        DB::table('orders')->where('id', $this->attendee['order_id'])->update(['email' => self::BUYER_EMAIL]);
        $this->orderShortId = DB::table('orders')->where('id', $this->attendee['order_id'])->value('short_id');

        $this->checkInListShortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $this->checkInListShortId,
            'name' => 'Door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }

    public function test_a_buyer_starts_a_transfer_and_the_recipient_is_invited_while_the_ticket_keeps_working(): void
    {
        Mail::fake();

        $this->startFromOrder(self::BUYER_EMAIL)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.recipient_email', 'r•••@example.test');

        Mail::assertQueued(TicketTransferInvitationMail::class, fn ($mail) => $mail->hasTo(self::RECIPIENT_EMAIL));
        $this->assertDatabaseHas('ticket_transfers', [
            'attendee_id' => $this->attendee['id'],
            'status' => TicketTransferStatus::PENDING->name,
            'sender_email' => self::BUYER_EMAIL,
            'recipient_email' => self::RECIPIENT_EMAIL,
        ]);
        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'public_id' => $this->attendee['public_id'], 'email' => self::GUEST_EMAIL]);
        $this->assertDatabaseHas('order_audit_logs', ['attendee_id' => $this->attendee['id'], 'action' => OrderAuditAction::TICKET_TRANSFER_STARTED->value]);

        $ticket = $this->getJson("/public/events/{$this->eventId}/attendees/{$this->attendee['short_id']}")->assertOk();
        $ticket->assertJsonPath('data.transfer.can_start', false);
        $ticket->assertJsonPath('data.transfer.pending.recipient_email', 'r•••@example.test');
    }

    public function test_the_invitation_carries_a_claim_link_and_nothing_typed_by_the_sender(): void
    {
        Mail::fake();
        $this->startFromOrder(self::BUYER_EMAIL)->assertStatus(ResponseCodes::HTTP_CREATED);

        Mail::assertQueued(TicketTransferInvitationMail::class, function (TicketTransferInvitationMail $mail) {
            $content = $mail->content()->with;

            return str_contains($content['claimUrl'], '/transfer/tt_')
                && $content['maskedSenderEmail'] === 'b•••@example.test'
                && ! str_contains($mail->render(), self::BUYER_EMAIL);
        });
    }

    public function test_a_transfer_needs_a_verified_session_for_someone_who_controls_the_ticket(): void
    {
        $this->postJson($this->orderTransferUrl(), ['recipient_email' => self::RECIPIENT_EMAIL])
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN)
            ->assertJsonPath('error_code', BaseTicketTransferPublicAction::VERIFICATION_REQUIRED);
        $this->postJson($this->orderTransferUrl(), ['recipient_email' => self::RECIPIENT_EMAIL], $this->selfServiceHeadersFor('stranger@example.test'))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->assertDatabaseMissing('ticket_transfers', ['attendee_id' => $this->attendee['id']]);
    }

    public function test_the_person_named_on_a_ticket_starts_a_transfer_from_their_ticket_link(): void
    {
        Mail::fake();

        $this->postJson($this->ticketTransferUrl(), ['recipient_email' => self::RECIPIENT_EMAIL], $this->selfServiceHeadersFor(self::GUEST_EMAIL))
            ->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'sender_email' => self::GUEST_EMAIL]);
    }

    public function test_a_transfer_is_refused_to_the_tickets_own_address_and_when_transfers_are_off(): void
    {
        $this->startFromOrder(self::BUYER_EMAIL, self::GUEST_EMAIL)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['recipient_email']);

        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_transfer' => false]);

        $this->startFromOrder(self::BUYER_EMAIL)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['recipient_email']);
        $this->getJson("/public/events/{$this->eventId}/attendees/{$this->attendee['short_id']}")
            ->assertJsonPath('data.transfer.can_start', false);
    }

    public function test_a_non_transferable_ticket_type_and_a_checked_in_ticket_cannot_be_transferred(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['is_transferable' => false]);
        $this->startFromOrder(self::BUYER_EMAIL)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        DB::table('products')->where('id', $this->productId)->update(['is_transferable' => true]);
        $this->checkIn($this->attendee['public_id'])->assertOk();
        $this->startFromOrder(self::BUYER_EMAIL)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_ticket_the_organizer_checked_in_by_hand_cannot_be_transferred(): void
    {
        DB::table('attendees')->where('id', $this->attendee['id'])->update(['checked_in_at' => now()]);

        $this->startFromOrder(self::BUYER_EMAIL)
            ->assertJsonValidationErrors(['recipient_email' => TicketTransferBlock::CHECKED_IN->message()]);
        $this->assertDatabaseMissing('ticket_transfers', ['attendee_id' => $this->attendee['id']]);
    }

    public function test_a_second_transfer_is_refused_while_one_is_pending_and_allowed_once_it_expires(): void
    {
        $this->insertTransfer('tt_first');
        $this->startFromOrder(self::BUYER_EMAIL, 'second@example.test')->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        DB::table('ticket_transfers')->where('attendee_id', $this->attendee['id'])->update(['expires_at' => now()->subMinute()]);
        Mail::fake();
        $this->startFromOrder(self::BUYER_EMAIL, 'second@example.test')->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->assertDatabaseHas('ticket_transfers', ['recipient_email' => self::RECIPIENT_EMAIL, 'status' => TicketTransferStatus::EXPIRED->name]);
        $this->assertDatabaseHas('ticket_transfers', ['recipient_email' => 'second@example.test', 'status' => TicketTransferStatus::PENDING->name]);
    }

    public function test_a_transfer_is_refused_once_the_per_ticket_limit_is_reached(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['max_transfers_per_ticket' => 1]);
        $this->insertTransfer('tt_done', status: TicketTransferStatus::ACCEPTED->name, createdAt: now()->subDays(3));

        $this->startFromOrder(self::BUYER_EMAIL)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_the_sender_cancels_a_pending_transfer_and_the_claim_link_stops_working(): void
    {
        $this->insertTransfer('tt_cancel');
        Mail::fake();

        $this->deleteJson($this->orderTransferUrl(), [], $this->selfServiceHeadersFor(self::BUYER_EMAIL))->assertNoContent();

        Mail::assertQueued(TicketTransferNoticeMail::class, fn ($mail) => $mail->hasTo(self::RECIPIENT_EMAIL));
        $this->getJson('/public/ticket-transfers/tt_cancel')
            ->assertOk()
            ->assertJsonPath('data.status', TicketTransferStatus::CANCELLED->name)
            ->assertJsonPath('data.can_accept', false);
        $this->accept('tt_cancel')->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->deleteJson($this->orderTransferUrl(), [], $this->selfServiceHeadersFor(self::BUYER_EMAIL))->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::GUEST_EMAIL]);
    }

    public function test_cancelling_needs_a_verified_session(): void
    {
        $this->insertTransfer('tt_keep');

        $this->deleteJson($this->orderTransferUrl())->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'status' => TicketTransferStatus::PENDING->name]);
    }

    public function test_the_claim_page_shows_the_event_and_ticket_without_revealing_the_senders_address(): void
    {
        $this->insertTransfer('tt_view');

        $claim = $this->getJson('/public/ticket-transfers/tt_view')->assertOk();

        $claim->assertJsonPath('data.status', TicketTransferStatus::PENDING->name);
        $claim->assertJsonPath('data.can_accept', true);
        $claim->assertJsonPath('data.recipient_email', self::RECIPIENT_EMAIL);
        $claim->assertJsonPath('data.sender_email', 'b•••@example.test');
        $claim->assertJsonPath('data.event_id', $this->eventId);
        $this->assertStringNotContainsString(self::BUYER_EMAIL, $claim->getContent());
        $this->assertStringNotContainsString($this->attendee['public_id'], $claim->getContent());
        $this->getJson('/public/ticket-transfers/tt_unknown')->assertNotFound();
    }

    public function test_accepting_gives_the_recipient_the_only_working_ticket(): void
    {
        $this->insertTransfer('tt_accept');

        $accepted = $this->accept('tt_accept')->assertOk();

        $current = DB::table('attendees')->where('id', $this->attendee['id'])->first();
        $accepted->assertExactJson(['event_id' => $this->eventId, 'attendee_short_id' => $current->short_id]);
        $this->assertSame(self::RECIPIENT_EMAIL, $current->email);
        $this->assertSame('Grace', $current->first_name);
        $this->assertSame('Hopper', $current->last_name);
        $this->assertNotNull($current->transferred_at);
        $this->assertNotSame($this->attendee['short_id'], $current->short_id);
        $this->assertNotSame($this->attendee['public_id'], $current->public_id);
        $this->checkIn($this->attendee['public_id'])->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->getJson("/public/events/{$this->eventId}/attendees/{$this->attendee['short_id']}")->assertNotFound();
        $this->checkIn($current->public_id)->assertOk();
        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'status' => TicketTransferStatus::ACCEPTED->name]);
        $this->assertDatabaseHas('order_audit_logs', ['attendee_id' => $this->attendee['id'], 'action' => OrderAuditAction::TICKET_TRANSFER_ACCEPTED->value]);
        $this->accept('tt_accept')->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_accepting_emails_the_new_holder_the_sender_and_the_buyer_and_tells_integrations(): void
    {
        $this->insertTransfer('tt_mails', senderEmail: self::GUEST_EMAIL);
        $events = [];
        $this->app['events']->listen(AttendeeEvent::class, function (AttendeeEvent $event) use (&$events): void {
            $events[] = $event->type;
        });
        Mail::fake();

        $this->accept('tt_mails')->assertOk();

        Mail::assertQueued(AttendeeTicketMail::class, fn ($mail) => $mail->hasTo(self::RECIPIENT_EMAIL));
        Mail::assertQueued(TicketTransferNoticeMail::class, fn ($mail) => $mail->hasTo(self::GUEST_EMAIL)
            && $mail->envelope()->subject === TicketTransferNotice::ACCEPTED_FOR_SENDER->subject());
        Mail::assertQueued(TicketTransferNoticeMail::class, fn ($mail) => $mail->hasTo(self::BUYER_EMAIL)
            && $mail->envelope()->subject === TicketTransferNotice::ACCEPTED_FOR_BUYER->subject());
        $this->assertContains(DomainEventType::ATTENDEE_TRANSFERRED, $events);
        $this->assertContains(DomainEventType::ATTENDEE_UPDATED, $events);
    }

    public function test_a_buyer_who_sent_the_transfer_is_not_emailed_twice(): void
    {
        $this->insertTransfer('tt_buyer');
        Mail::fake();

        $this->accept('tt_buyer')->assertOk();

        Mail::assertQueued(TicketTransferNoticeMail::class, 1);
    }

    public function test_an_invalid_name_is_refused_when_accepting(): void
    {
        $this->insertTransfer('tt_name');

        $this->postJson('/public/ticket-transfers/tt_name/accept', ['first_name' => 'Visit https://spam.example', 'last_name' => 'Hopper'])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['first_name']);
        $this->postJson('/public/ticket-transfers/tt_name/accept', ['last_name' => 'Hopper'])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['first_name']);

        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::GUEST_EMAIL]);
    }

    public function test_required_ticket_questions_must_be_answered_and_replace_the_previous_answers(): void
    {
        $questionId = $this->postJson("/events/{$this->eventId}/questions", [
            'title' => 'Dietary needs',
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => 'PRODUCT',
            'product_ids' => [$this->productId],
            'required' => true,
            'is_hidden' => false,
        ], $this->organizerHeaders())->assertCreated()->json('data.id');
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        DB::table('question_answers')->insert([
            'question_id' => $questionId,
            'order_id' => $this->attendee['order_id'],
            'attendee_id' => $this->attendee['id'],
            'product_id' => $this->productId,
            'answer' => json_encode('Vegan'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertTransfer('tt_questions');

        $this->getJson('/public/ticket-transfers/tt_questions')->assertOk()->assertJsonPath('data.questions.0.id', $questionId);
        $this->accept('tt_questions')->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        $this->accept('tt_questions', [['question_id' => $questionId, 'response' => ['answer' => 'No nuts']]])->assertOk();

        $answers = DB::table('question_answers')->where('attendee_id', $this->attendee['id'])->pluck('answer');
        $this->assertCount(1, $answers);
        $this->assertStringContainsString('No nuts', $answers->first());
    }

    public function test_declining_leaves_the_ticket_with_the_sender_and_tells_them(): void
    {
        $this->insertTransfer('tt_decline');
        Mail::fake();

        $this->postJson('/public/ticket-transfers/tt_decline/decline')->assertNoContent();

        Mail::assertQueued(TicketTransferNoticeMail::class, fn ($mail) => $mail->hasTo(self::BUYER_EMAIL)
            && $mail->envelope()->subject === TicketTransferNotice::DECLINED_FOR_SENDER->subject());
        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'status' => TicketTransferStatus::DECLINED->name]);
        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::GUEST_EMAIL, 'public_id' => $this->attendee['public_id']]);
        $this->accept('tt_decline')->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_an_expired_transfer_cannot_be_accepted_and_the_sender_keeps_the_ticket(): void
    {
        $this->insertTransfer('tt_late', expiresAt: now()->subMinute());

        $this->getJson('/public/ticket-transfers/tt_late')
            ->assertOk()
            ->assertJsonPath('data.status', TicketTransferStatus::EXPIRED->name)
            ->assertJsonPath('data.can_accept', false);
        $this->accept('tt_late')->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->checkIn($this->attendee['public_id'])->assertOk();
    }

    public function test_a_ticket_checked_in_or_cancelled_while_the_transfer_was_pending_cannot_be_accepted(): void
    {
        $this->insertTransfer('tt_used');
        $this->checkIn($this->attendee['public_id'])->assertOk();

        $this->getJson('/public/ticket-transfers/tt_used')->assertJsonPath('data.can_accept', false);
        $this->accept('tt_used')->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::GUEST_EMAIL, 'public_id' => $this->attendee['public_id']]);
    }

    public function test_an_organizer_reassigning_the_ticket_cancels_its_pending_transfer(): void
    {
        $this->insertTransfer('tt_reassigned');
        Mail::fake();

        $this->patchJson("/events/{$this->eventId}/attendees/{$this->attendee['id']}", ['email' => 'new.holder@example.test'], $this->organizerHeaders())
            ->assertOk();

        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'status' => TicketTransferStatus::CANCELLED->name]);
        Mail::assertQueued(TicketTransferNoticeMail::class, fn ($mail) => $mail->hasTo(self::RECIPIENT_EMAIL));
        $this->accept('tt_reassigned')->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => 'new.holder@example.test']);
    }

    public function test_a_buyer_changing_the_tickets_email_cancels_its_pending_transfer(): void
    {
        $this->insertTransfer('tt_edited');
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        $this->patchJson(
            "/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/{$this->attendee['short_id']}",
            ['email' => 'friend@example.test'],
            $this->selfServiceHeadersFor(self::BUYER_EMAIL),
        )->assertOk();

        $this->assertDatabaseHas('ticket_transfers', ['attendee_id' => $this->attendee['id'], 'status' => TicketTransferStatus::CANCELLED->name]);
        $this->accept('tt_edited')->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_a_transferred_ticket_leaves_the_buyers_order_page_and_their_control(): void
    {
        $this->insertTransfer('tt_gone');
        $newShortId = $this->accept('tt_gone')->assertOk()->json('attendee_short_id');

        $order = $this->getJson("/public/events/{$this->eventId}/order/{$this->orderShortId}?include=event")->assertOk();

        $order->assertJsonCount(0, 'data.attendees');
        $order->assertJsonPath('data.transferred_tickets.0.recipient_email', 'r•••@example.test');
        $this->assertStringNotContainsString($newShortId, $order->getContent());
        $this->assertStringNotContainsString(DB::table('attendees')->where('id', $this->attendee['id'])->value('public_id'), $order->getContent());
        $buyer = $this->selfServiceHeadersFor(self::BUYER_EMAIL);
        $this->patchJson("/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/$newShortId", ['first_name' => 'Mine'], $buyer)->assertNotFound();
        $this->postJson("/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/$newShortId/resend-ticket", [], $buyer)->assertNotFound();
        $this->postJson("/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/$newShortId/transfer", ['recipient_email' => 'third@example.test'], $buyer)
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->postJson("/public/events/{$this->eventId}/attendees/$newShortId/transfer", ['recipient_email' => 'third@example.test'], $buyer)
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    public function test_a_ticket_transferred_back_to_the_buyer_shows_on_their_order_as_a_normal_ticket(): void
    {
        $this->insertTransfer('tt_back', senderEmail: self::GUEST_EMAIL, recipientEmail: self::BUYER_EMAIL);
        $this->accept('tt_back')->assertOk();

        $order = $this->getJson("/public/events/{$this->eventId}/order/{$this->orderShortId}?include=event")->assertOk();

        $order->assertJsonCount(1, 'data.attendees');
        $order->assertJsonCount(0, 'data.transferred_tickets');
    }

    public function test_the_new_holder_can_transfer_the_ticket_on_again(): void
    {
        $this->insertTransfer('tt_first_hop', createdAt: now()->subDays(2));
        $newShortId = $this->accept('tt_first_hop')->assertOk()->json('attendee_short_id');
        Mail::fake();

        $this->postJson(
            "/public/events/{$this->eventId}/attendees/$newShortId/transfer",
            ['recipient_email' => 'third@example.test'],
            $this->selfServiceHeadersFor(self::RECIPIENT_EMAIL),
        )->assertStatus(ResponseCodes::HTTP_CREATED);
    }

    private function startFromOrder(string $verifiedEmail, string $recipientEmail = self::RECIPIENT_EMAIL)
    {
        return $this->postJson($this->orderTransferUrl(), ['recipient_email' => $recipientEmail], $this->selfServiceHeadersFor($verifiedEmail));
    }

    private function accept(string $token, array $questions = [])
    {
        return $this->postJson("/public/ticket-transfers/$token/accept", [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'questions' => $questions,
        ]);
    }

    private function insertTransfer(
        string $token,
        string $status = 'PENDING',
        string $senderEmail = self::BUYER_EMAIL,
        string $recipientEmail = self::RECIPIENT_EMAIL,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $createdAt = null,
    ): void {
        DB::table('ticket_transfers')->insert([
            'event_id' => $this->eventId,
            'order_id' => $this->attendee['order_id'],
            'attendee_id' => $this->attendee['id'],
            'sender_email' => $senderEmail,
            'recipient_email' => $recipientEmail,
            'token_hash' => TicketTransferClaimService::hashToken($token),
            'status' => $status,
            'locale' => 'en',
            'expires_at' => $expiresAt ?? now()->addDay(),
            'created_at' => $createdAt ?? now()->subMinutes(5),
            'updated_at' => now(),
        ]);
    }

    private function orderTransferUrl(): string
    {
        return "/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/{$this->attendee['short_id']}/transfer";
    }

    private function ticketTransferUrl(): string
    {
        return "/public/events/{$this->eventId}/attendees/{$this->attendee['short_id']}/transfer";
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
