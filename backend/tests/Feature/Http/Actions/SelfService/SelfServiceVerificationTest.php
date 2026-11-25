<?php

namespace Tests\Feature\Http\Actions\SelfService;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\SelfService\SelfServiceVerificationCodeMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\VerifiesSelfService;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class SelfServiceVerificationTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use VerifiesSelfService;

    private const BUYER_EMAIL = 'buyer@example.test';

    private const GUEST_EMAIL = 'guest@example.test';

    private const NEW_EMAIL = 'someone-else@example.test';

    private array $attendee;

    private string $orderShortId;

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

        $productId = $this->insertProduct(priceType: 'FREE');
        $this->attendee = $this->postJson("/events/{$this->eventId}/attendees", [
            'product_id' => $productId,
            'product_price_id' => $this->insertPrice($productId, null),
            'event_occurrence_id' => $this->insertOccurrence(),
            'email' => self::GUEST_EMAIL,
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'amount_paid' => 0,
            'send_confirmation_email' => false,
            'locale' => 'en',
        ], $this->headersFor(User::find($this->userId), $this->accountId))
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->json('data');

        DB::table('orders')->where('id', $this->attendee['order_id'])->update(['email' => self::BUYER_EMAIL]);
        $this->orderShortId = DB::table('orders')->where('id', $this->attendee['order_id'])->value('short_id');
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }

    public function test_a_code_requested_from_an_order_link_goes_to_the_buyer_and_shows_a_masked_address(): void
    {
        Mail::fake();

        $this->postJson($this->orderUrl('verification'))
            ->assertOk()
            ->assertExactJson(['email' => 'b•••@example.test']);

        Mail::assertQueued(SelfServiceVerificationCodeMail::class, fn ($mail) => $mail->hasTo(self::BUYER_EMAIL));
        Mail::assertNotQueued(SelfServiceVerificationCodeMail::class, fn ($mail) => $mail->hasTo(self::GUEST_EMAIL));
    }

    public function test_a_code_requested_from_a_ticket_link_goes_to_the_person_named_on_the_ticket(): void
    {
        Mail::fake();

        $this->postJson($this->attendeeUrl('verification'))
            ->assertOk()
            ->assertExactJson(['email' => 'g•••@example.test']);

        Mail::assertQueued(SelfServiceVerificationCodeMail::class, fn ($mail) => $mail->hasTo(self::GUEST_EMAIL));
    }

    public function test_a_ticket_with_no_email_on_file_cannot_be_verified(): void
    {
        Mail::fake();
        DB::table('attendees')->where('id', $this->attendee['id'])->update(['email' => null]);

        $this->postJson($this->attendeeUrl('verification'))
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_link_is_not_found_and_sends_nothing(): void
    {
        Mail::fake();

        $this->postJson("/public/events/{$this->eventId}/order/o_missing/verification")->assertNotFound();
        $this->postJson("/public/events/{$this->eventId}/attendees/a_missing/verification")->assertNotFound();

        Mail::assertNothingQueued();
    }

    public function test_the_correct_code_opens_a_session_that_allows_an_email_change(): void
    {
        $token = $this->confirm($this->requestOrderCode())->assertOk()->json('data.token');

        $this->patchJson($this->orderUrl("attendees/{$this->attendee['short_id']}"), ['email' => self::NEW_EMAIL], [
            SelfServiceVerificationService::SESSION_HEADER => $token,
        ])->assertOk();

        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::NEW_EMAIL]);
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_codes_lock_the_code(): void
    {
        $code = $this->requestOrderCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, SelfServiceVerificationService::MAX_ATTEMPTS) as $attempt) {
            $this->confirm($wrong)
                ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['code']);
        }

        $this->confirm($code)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_a_code_stops_working_after_ten_minutes(): void
    {
        $code = $this->requestOrderCode();

        $this->travel(SelfServiceVerificationService::CODE_TTL_MINUTES + 1)->minutes();

        $this->confirm($code)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $code = $this->requestOrderCode();
        $this->confirm($code)->assertOk();

        $this->confirm($code)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_session_stops_working_after_thirty_minutes(): void
    {
        $headers = $this->selfServiceHeadersFor(self::BUYER_EMAIL);

        $this->travel(SelfServiceVerificationService::SESSION_TTL_MINUTES + 1)->minutes();

        $this->patchJson($this->orderUrl("attendees/{$this->attendee['short_id']}"), ['email' => self::NEW_EMAIL], $headers)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_changing_a_ticket_email_needs_a_session_but_changing_only_the_name_does_not(): void
    {
        $url = $this->orderUrl("attendees/{$this->attendee['short_id']}");

        $this->patchJson($url, ['email' => self::NEW_EMAIL])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);
        $this->patchJson($url, ['first_name' => 'Augusta', 'email' => self::GUEST_EMAIL])->assertOk();

        $this->assertDatabaseHas('attendees', ['id' => $this->attendee['id'], 'email' => self::GUEST_EMAIL, 'first_name' => 'Augusta']);
    }

    public function test_a_session_for_an_unrelated_address_cannot_change_a_ticket_email(): void
    {
        $this->patchJson(
            $this->orderUrl("attendees/{$this->attendee['short_id']}"),
            ['email' => self::NEW_EMAIL],
            $this->selfServiceHeadersFor('stranger@example.test'),
        )->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)->assertJsonValidationErrors(['email']);
    }

    public function test_the_person_named_on_a_ticket_can_change_its_email_with_their_own_session(): void
    {
        $this->patchJson(
            $this->orderUrl("attendees/{$this->attendee['short_id']}"),
            ['email' => self::NEW_EMAIL],
            $this->selfServiceHeadersFor(self::GUEST_EMAIL),
        )->assertOk();
    }

    public function test_an_order_link_alone_cannot_redirect_the_order_to_another_address(): void
    {
        $this->patchJson($this->orderUrl(), ['email' => self::NEW_EMAIL])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('orders', ['id' => $this->attendee['order_id'], 'email' => self::BUYER_EMAIL, 'short_id' => $this->orderShortId]);

        Mail::fake();
        $this->postJson($this->orderUrl('verification'))->assertOk();
        Mail::assertQueued(SelfServiceVerificationCodeMail::class, fn ($mail) => $mail->hasTo(self::BUYER_EMAIL));
        Mail::assertNotQueued(SelfServiceVerificationCodeMail::class, fn ($mail) => $mail->hasTo(self::NEW_EMAIL));
    }

    public function test_a_verified_buyer_can_change_the_order_email_and_a_name_change_needs_no_session(): void
    {
        $this->patchJson($this->orderUrl(), ['first_name' => 'Grace'])->assertOk();

        $this->patchJson($this->orderUrl(), ['email' => self::NEW_EMAIL], $this->selfServiceHeadersFor(self::BUYER_EMAIL))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $this->attendee['order_id'], 'email' => self::NEW_EMAIL, 'first_name' => 'Grace']);
    }

    public function test_a_ticket_lookup_link_opens_a_session_for_its_address(): void
    {
        DB::table('ticket_lookup_tokens')->insert([
            ['email' => self::BUYER_EMAIL, 'token' => 'tl_valid', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()],
            ['email' => self::BUYER_EMAIL, 'token' => 'tl_expired', 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $token = $this->postJson('/public/ticket-lookup/tl_valid/session')
            ->assertOk()
            ->assertJsonPath('data.email', 'b•••@example.test')
            ->json('data.token');

        $this->patchJson($this->orderUrl(), ['email' => self::NEW_EMAIL], [SelfServiceVerificationService::SESSION_HEADER => $token])->assertOk();
        $this->postJson('/public/ticket-lookup/tl_expired/session')->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
        $this->postJson('/public/ticket-lookup/tl_unknown/session')->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
    }

    private function requestOrderCode(): string
    {
        Mail::fake();
        $this->postJson($this->orderUrl('verification'))->assertOk();

        $code = null;
        Mail::assertQueued(SelfServiceVerificationCodeMail::class, function (SelfServiceVerificationCodeMail $mail) use (&$code) {
            $code = $mail->content()->with['code'];

            return true;
        });

        return $code;
    }

    private function confirm(string $code)
    {
        return $this->postJson($this->orderUrl('verification/confirm'), ['code' => $code]);
    }

    private function orderUrl(string $path = ''): string
    {
        return rtrim("/public/events/{$this->eventId}/order/{$this->orderShortId}/$path", '/');
    }

    private function attendeeUrl(string $path): string
    {
        return "/public/events/{$this->eventId}/attendees/{$this->attendee['short_id']}/$path";
    }
}
