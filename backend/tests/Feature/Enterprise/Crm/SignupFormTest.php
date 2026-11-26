<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Jobs\PruneCrmPendingSubscriptionsJob;
use HiEvents\Enterprise\Crm\Mail\SubscriptionConfirmationMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\SubmitSignupFormHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupSubmissionLimiter;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SignupFormTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private string $publicId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.secret_key' => null]);
        Cache::flush();
        Mail::fake();
        $this->setUpCrmApi();
        $this->setOrganizerStatus($this->organizerId, 'LIVE');
        $this->publicId = (string) DB::table('crm_signup_forms')->where('id', $this->signupFormId)->value('public_id');
    }

    public function test_a_form_is_created_with_an_unguessable_public_id_and_listed(): void
    {
        $form = $this->crm('POST', 'forms', $this->signupFormBody(['name' => ' Main list ', 'tag_ids' => [$this->tagId], 'asks_for_name' => true]), $this->ownerHeaders())
            ->assertCreated()
            ->json('data');

        $this->assertMatchesRegularExpression('/^sf_[a-z0-9]{16}$/', $form['public_id']);
        $this->assertSame(['Main list', [$this->tagId], true, 'ACTIVE', 0], [$form['name'], $form['tag_ids'], $form['asks_for_name'], $form['status'], $form['confirmations_count']]);
        $this->assertContains($form['id'], array_column($this->crm('GET', 'forms', [], $this->ownerHeaders())->json('data'), 'id'));
    }

    public function test_form_text_cannot_contain_links_or_markup(): void
    {
        foreach (['title' => 'Win at www.example.com', 'description' => 'Visit https://example.com', 'button_label' => '<b>Go</b>', 'success_message' => 'See prizes.ru'] as $field => $value) {
            $this->crm('POST', 'forms', $this->signupFormBody([$field => $value]), $this->ownerHeaders())
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->crm('POST', 'forms', $this->signupFormBody(['privacy_url' => 'http://example.com/privacy']), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('privacy_url');
    }

    public function test_tags_must_belong_to_the_organizer(): void
    {
        $this->crm('POST', 'forms', $this->signupFormBody(['tag_ids' => [$this->otherTagId]]), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids');
        $this->crm('PUT', "forms/{$this->signupFormId}", $this->signupFormBody(['tag_ids' => [$this->otherTagId]]), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids');
    }

    public function test_only_one_form_is_shown_on_the_organizer_page(): void
    {
        $this->crm('PUT', "forms/{$this->signupFormId}", $this->signupFormBody(['show_on_organizer_page' => true]), $this->ownerHeaders())->assertOk();
        $second = $this->crm('POST', 'forms', $this->signupFormBody(['name' => 'Second', 'title' => 'Second list', 'show_on_organizer_page' => true]), $this->ownerHeaders())->json('data.id');

        $this->assertSame([$second], DB::table('crm_signup_forms')->where('show_on_organizer_page', true)->pluck('id')->map(static fn ($id) => (int) $id)->all());
        $this->json('GET', "/public/organizers/{$this->organizerId}/signup-form")->assertOk()->assertJsonPath('data.title', 'Second list');
    }

    public function test_another_organizers_form_cannot_be_changed_or_deleted(): void
    {
        $theirs = $this->insertSignupForm($this->otherOrganizerId);

        $this->crm('PUT', "forms/$theirs", $this->signupFormBody(), $this->ownerHeaders())->assertNotFound();
        $this->crm('DELETE', "forms/$theirs", [], $this->ownerHeaders())->assertNotFound();

        $this->assertNull(DB::table('crm_signup_forms')->where('id', $theirs)->value('deleted_at'));
    }

    public function test_only_active_forms_of_accounts_with_audience_are_public(): void
    {
        $this->json('GET', "/public/crm/forms/{$this->publicId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Get our news')
            ->assertJsonPath('data.organizer_name', DB::table('organizers')->where('id', $this->organizerId)->value('name'))
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tag_ids');

        DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['status' => 'PAUSED']);
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertNotFound();

        DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['status' => 'ACTIVE']);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertNotFound();

        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, true);
        $this->crm('DELETE', "forms/{$this->signupFormId}", [], $this->ownerHeaders())->assertNoContent();
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertNotFound();
        $this->json('GET', '/public/crm/forms/sf_doesnotexist000')->assertNotFound();
    }

    public function test_a_signup_creates_a_pending_subscription_and_emails_a_confirmation_link(): void
    {
        DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['asks_for_name' => true]);

        $this->submit(['email' => ' New.Fan@Example.com ', 'first_name' => 'Nia', 'last_name' => 'Fan'])
            ->assertStatus(202)
            ->assertExactJson(['status' => 'check_inbox']);

        $pending = DB::table('crm_pending_subscriptions')->sole();
        $this->assertSame(['new.fan@example.com', 'Nia', 'Fan', 'PENDING', $this->signupFormId], [$pending->email, $pending->first_name, $pending->last_name, $pending->status, (int) $pending->form_id]);
        $this->assertSame(1, (int) DB::table('crm_signup_forms')->where('id', $this->signupFormId)->value('submissions_count'));
        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'new.fan@example.com')->count());
        Mail::assertQueued(SubscriptionConfirmationMail::class, static fn (SubscriptionConfirmationMail $mail) => $mail->hasTo('new.fan@example.com'));
    }

    public function test_an_organizer_name_cannot_add_headings_or_links_to_the_confirmation_email(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => "Acme\n\n# Card declined\n\n[Fix it](mailto:billing@phish.example)\n\n![](//phish.example/pixel)"]);

        $this->submit(['email' => 'fan@example.com'])->assertStatus(202);

        Mail::assertQueued(SubscriptionConfirmationMail::class, static function (SubscriptionConfirmationMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Acme # Card declined [Fix it](mailto:billing@phish.example)')
                && ! str_contains($html, 'Card declined</h1>')
                && ! str_contains($html, 'href="mailto:billing@phish.example"')
                && ! str_contains($html, 'phish.example/pixel"')
                && ! str_contains($mail->envelope()->subject, "\n");
        });
    }

    public function test_the_confirmation_email_says_how_long_the_link_lasts_and_the_link_expires_then(): void
    {
        $this->freezeSecond();
        $this->submit(['email' => 'fan@example.com'])->assertStatus(202);

        Mail::assertQueued(SubscriptionConfirmationMail::class, static fn (SubscriptionConfirmationMail $mail) => str_contains(
            $mail->render(),
            'This link expires in '.SubmitSignupFormHandler::CONFIRMATION_DAYS.' days.',
        ));
        $this->assertSame(
            now()->addDays(SubmitSignupFormHandler::CONFIRMATION_DAYS)->toDateTimeString(),
            Carbon::parse(DB::table('crm_pending_subscriptions')->sole()->expires_at)->toDateTimeString(),
        );
    }

    public function test_signing_up_again_counts_one_signup_and_waiting_counts_only_unexpired_links(): void
    {
        Cache::flush();
        $this->submit(['email' => 'fan@example.com'])->assertStatus(202);
        $this->travel(2)->minutes();
        Cache::flush();
        $this->submit(['email' => 'fan@example.com'])->assertStatus(202);

        $form = collect($this->crm('GET', 'forms', [], $this->ownerHeaders())->json('data'))->firstWhere('id', $this->signupFormId);
        $this->assertSame(1, $form['waiting_count']);

        $this->travel(8)->days();
        $form = collect($this->crm('GET', 'forms', [], $this->ownerHeaders())->json('data'))->firstWhere('id', $this->signupFormId);
        $this->assertSame(0, $form['waiting_count']);
    }

    public function test_bots_and_repeat_submissions_get_the_same_answer_but_no_email(): void
    {
        $this->submit(['email' => 'bot@example.com', 'website' => 'https://spam.example'])->assertStatus(202)->assertExactJson(['status' => 'check_inbox']);
        $this->assertSame(0, DB::table('crm_pending_subscriptions')->count());

        foreach (range(1, 5) as $attempt) {
            $this->submit(['email' => 'eager@example.com'])->assertStatus(202)->assertExactJson(['status' => 'check_inbox']);
        }

        $this->assertSame(3, DB::table('crm_pending_subscriptions')->where('email', 'eager@example.com')->count());
        $this->assertSame(1, DB::table('crm_pending_subscriptions')->where('email', 'eager@example.com')->where('status', 'PENDING')->count());
        Mail::assertQueuedCount(3);
    }

    public function test_a_form_that_reached_its_hourly_limit_answers_the_same_but_sends_no_email(): void
    {
        $limiter = app(SignupSubmissionLimiter::class);
        foreach (range(1, SignupSubmissionLimiter::MAX_PER_FORM_PER_HOUR) as $number) {
            $limiter->attempt($this->organizerId, $this->signupFormId, "fan$number@example.com");
        }

        $this->submit(['email' => 'late@example.com'])->assertStatus(202)->assertExactJson(['status' => 'check_inbox']);

        $this->assertSame(0, DB::table('crm_pending_subscriptions')->count());
        Mail::assertNothingQueued();
    }

    public function test_someone_else_submitting_an_address_over_and_over_does_not_lock_its_owner_out(): void
    {
        foreach (range(1, 4) as $attempt) {
            $this->submit(['email' => 'target@example.com'])->assertStatus(202)->assertExactJson(['status' => 'check_inbox']);
        }
        $this->travel(61)->minutes();
        foreach (range(1, 4) as $attempt) {
            $this->submit(['email' => 'target@example.com'])->assertStatus(202)->assertExactJson(['status' => 'check_inbox']);
        }
        Mail::assertQueuedCount(SignupSubmissionLimiter::MAX_PER_ORGANIZER_PER_DAY);
        $linkFromTheFirstOrganizer = $this->tokenFromMail('target@example.com');

        $otherOrganizersForm = $this->formOfAnotherOrganizer('Second Organizer');
        $this->json('POST', "/public/crm/forms/$otherOrganizersForm/submissions", ['email' => 'target@example.com'])
            ->assertStatus(202)
            ->assertExactJson(['status' => 'check_inbox']);
        Mail::assertQueuedCount(SignupSubmissionLimiter::MAX_PER_ORGANIZER_PER_DAY + 1);

        $this->json('POST', "/public/crm/confirm/$linkFromTheFirstOrganizer")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame(
            'SUBSCRIBED',
            DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'target@example.com')->value('email_marketing_status'),
        );
    }

    public function test_an_address_is_emailed_by_at_most_five_organizers_a_day_and_the_next_one_answers_the_same(): void
    {
        $forms = [$this->publicId];
        foreach (range(2, SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY + 1) as $number) {
            $forms[] = $this->formOfAnotherOrganizer("Organizer $number");
        }

        foreach ($forms as $publicId) {
            $this->json('POST', "/public/crm/forms/$publicId/submissions", ['email' => 'target@example.com'])
                ->assertStatus(202)
                ->assertExactJson(['status' => 'check_inbox']);
        }

        Mail::assertQueuedCount(SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY);
        $this->assertSame(SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY, DB::table('crm_pending_subscriptions')->where('email', 'target@example.com')->count());
    }

    public function test_a_failed_bot_check_is_rejected_when_turnstile_is_configured(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->submit(['email' => 'person@example.com', 'turnstile_token' => 'bad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('turnstile_token');
        $this->submit(['email' => 'person@example.com'])->assertUnprocessable();

        $this->assertSame(0, DB::table('crm_pending_subscriptions')->count());
        Mail::assertNothingQueued();
    }

    public function test_a_signup_is_rejected_when_the_bot_check_cannot_be_reached(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => static fn () => throw new ConnectionException('Connection timed out')]);

        $this->submit(['email' => 'person@example.com', 'turnstile_token' => 'token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('turnstile_token');

        $this->assertSame(0, DB::table('crm_pending_subscriptions')->count());
        Mail::assertNothingQueued();
    }

    public function test_the_emailed_link_confirms_the_subscription_once(): void
    {
        $this->submit(['email' => 'reader@example.com'])->assertStatus(202);
        $token = $this->tokenFromMail('reader@example.com');

        $this->json('GET', "/public/crm/confirm/$token")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.masked_email', 'r•••••@example.com')
            ->assertJsonPath('data.organizer_name', 'Test Organizer');
        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'reader@example.com')->count());

        $this->json('POST', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->json('POST', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $contact = DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'reader@example.com')->sole();
        $this->assertSame(['FORM', 'SUBSCRIBED'], [$contact->source, $contact->email_marketing_status]);
        $this->assertSame(1, DB::table('crm_consent_events')->where(['contact_id' => $contact->id, 'action' => 'OPT_IN', 'source_type' => 'FORM'])->count());
        $this->assertSame(1, (int) DB::table('crm_signup_forms')->where('id', $this->signupFormId)->value('confirmations_count'));
    }

    public function test_confirming_applies_the_forms_tags_and_fills_a_missing_name(): void
    {
        DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['tag_ids' => json_encode([$this->tagId, $this->otherTagId])]);
        $token = $this->pendingToken('second@example.com', ['first_name' => 'Sam', 'last_name' => 'Second']);
        DB::table('crm_contacts')->where('id', $this->secondContactId)->update(['first_name' => null, 'last_name' => null]);

        $this->json('POST', "/public/crm/confirm/$token")->assertOk();

        $contact = DB::table('crm_contacts')->where('id', $this->secondContactId)->first();
        $this->assertSame(['Sam', 'Second', 'SUBSCRIBED'], [$contact->first_name, $contact->last_name, $contact->email_marketing_status]);
        $this->assertSame([$this->tagId], DB::table('crm_contact_tags')->where('contact_id', $this->secondContactId)->pluck('tag_id')->map(static fn ($id) => (int) $id)->all());
    }

    public function test_an_old_link_has_expired_and_an_unknown_one_is_invalid(): void
    {
        $expired = $this->pendingToken('late@example.com', ['expires_at' => now()->subMinute()]);

        $this->json('POST', "/public/crm/confirm/$expired")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.form_public_id', $this->publicId)
            ->assertJsonPath('data.link_lifetime_days', SubmitSignupFormHandler::CONFIRMATION_DAYS);
        $this->json('GET', '/public/crm/confirm/'.str_repeat('x', 43))->assertOk()->assertExactJson(['data' => ['status' => 'invalid', 'organizer_id' => null, 'organizer_name' => null, 'masked_email' => null, 'success_message' => null, 'form_public_id' => null, 'link_lifetime_days' => SubmitSignupFormHandler::CONFIRMATION_DAYS]]);

        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'late@example.com')->count());
    }

    public function test_the_link_from_an_earlier_signup_still_confirms_and_retires_the_newer_one(): void
    {
        $earlier = $this->pendingToken('twice@example.com', ['status' => 'SUPERSEDED']);
        $this->pendingToken('twice@example.com');

        $this->json('POST', "/public/crm/confirm/$earlier")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('SUBSCRIBED', DB::table('crm_contacts')->where('email', 'twice@example.com')->value('email_marketing_status'));
        $this->assertSame(['CONFIRMED', 'SUPERSEDED'], DB::table('crm_pending_subscriptions')->where('email', 'twice@example.com')->orderBy('id')->pluck('status')->all());
    }

    public function test_a_retired_link_reports_the_subscription_as_confirmed_without_confirming_it_again(): void
    {
        $earlier = $this->pendingToken('twice@example.com', ['status' => 'SUPERSEDED', 'created_at' => now()->subHour()]);
        $newer = $this->pendingToken('twice@example.com');

        $this->json('POST', "/public/crm/confirm/$newer")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->json('GET', "/public/crm/confirm/$earlier")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->json('POST', "/public/crm/confirm/$earlier")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(1, DB::table('crm_consent_events')->where(['email' => 'twice@example.com', 'action' => 'OPT_IN', 'source_type' => 'FORM'])->count());
        $this->assertSame(1, (int) DB::table('crm_signup_forms')->where('id', $this->signupFormId)->value('confirmations_count'));
        $this->assertSame(['SUPERSEDED', 'CONFIRMED'], DB::table('crm_pending_subscriptions')->where('email', 'twice@example.com')->orderBy('id')->pluck('status')->all());
    }

    public function test_a_link_from_before_an_unrelated_earlier_confirmation_still_confirms(): void
    {
        $this->pendingToken('twice@example.com', ['status' => 'CONFIRMED', 'created_at' => now()->subDays(3), 'confirmed_at' => now()->subDays(2)]);
        $later = $this->pendingToken('twice@example.com', ['status' => 'SUPERSEDED', 'created_at' => now()->subDay()]);

        $this->json('POST', "/public/crm/confirm/$later")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(1, DB::table('crm_consent_events')->where(['email' => 'twice@example.com', 'action' => 'OPT_IN', 'source_type' => 'FORM'])->count());
    }

    public function test_a_link_stops_working_once_the_account_no_longer_has_the_audience_feature(): void
    {
        $token = $this->pendingToken('fan@example.com');
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);

        $this->json('GET', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'invalid');
        $this->json('POST', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'invalid');

        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'fan@example.com')->count());
    }

    public function test_a_confirmed_signup_resubscribes_someone_who_unsubscribed_bounced_or_complained(): void
    {
        foreach (['OPT_OUT', 'BOUNCED', 'COMPLAINED'] as $action) {
            $email = strtolower($action).'@example.com';
            $contactId = $this->insertContact($email);
            $this->insertConsent($contactId, $email, 'OPT_IN', now()->subDays(30));
            $this->insertConsent($contactId, $email, $action, now()->subDays(10));
            app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$contactId]);
            $this->assertNotSame('SUBSCRIBED', DB::table('crm_contacts')->where('id', $contactId)->value('email_marketing_status'));

            $this->json('POST', '/public/crm/confirm/'.$this->pendingToken($email))->assertOk()->assertJsonPath('data.status', 'confirmed');

            $this->assertSame('SUBSCRIBED', DB::table('crm_contacts')->where('id', $contactId)->value('email_marketing_status'), $action);
        }
    }

    public function test_an_import_opt_in_does_not_override_a_complaint(): void
    {
        $contactId = $this->insertContact('angry@example.com');
        $this->insertConsent($contactId, 'angry@example.com', 'COMPLAINED', now()->subDays(10));
        DB::table('crm_consent_events')->insert(['organizer_id' => $this->organizerId, 'contact_id' => $contactId, 'email' => 'angry@example.com', 'action' => 'OPT_IN', 'source_type' => 'IMPORT', 'occurred_at' => now()]);

        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$contactId]);

        $this->assertSame('COMPLAINED', DB::table('crm_contacts')->where('id', $contactId)->value('email_marketing_status'));
    }

    public function test_confirming_from_a_secondary_address_makes_it_the_primary_one(): void
    {
        $this->json('POST', '/public/crm/confirm/'.$this->pendingToken('alias@example.com'))->assertOk();

        $contact = DB::table('crm_contacts')->where('id', $this->contactId)->first();
        $this->assertSame(['alias@example.com', 'SUBSCRIBED'], [$contact->email, $contact->email_marketing_status]);
    }

    public function test_a_deleted_contact_who_signs_up_again_is_restored_with_their_history(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();
        $hasher = app(DeletedContactHasher::class);
        $this->assertSame(1, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->where('email_hash', $hasher->hash($this->organizerId, 'ada@example.com'))->count());

        $this->json('POST', '/public/crm/confirm/'.$this->pendingToken('ada@example.com'))->assertOk()->assertJsonPath('data.status', 'confirmed');

        $contact = DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'ada@example.com')->sole();
        $this->assertSame('SUBSCRIBED', $contact->email_marketing_status);
        $this->assertSame(0, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->where('email_hash', $hasher->hash($this->organizerId, 'ada@example.com'))->count());
        $this->assertGreaterThan(0, DB::table('crm_contact_activities')->where('contact_id', $contact->id)->where('type', 'ORDER_COMPLETED')->count());
    }

    public function test_a_deleted_contact_who_had_opted_out_of_texts_stays_opted_out_when_a_signup_restores_them(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['phone' => '+353851234567', 'sms_opted_in_at' => now()->subDays(5)]);
        $this->projectOrders($this->orderId);
        app(ConsentRecorder::class)->recordSmsConsent($this->organizerId, $this->contactId, null, CrmConsentAction::OPT_OUT, CrmConsentSource::SMS_REPLY);
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->json('POST', '/public/crm/confirm/'.$this->pendingToken('ada@example.com'))->assertOk()->assertJsonPath('data.status', 'confirmed');

        $contact = $this->contact('ada@example.com');
        $this->assertSame(['+353851234567', 'UNSUBSCRIBED', 'SUBSCRIBED'], [$contact->phone, $contact->sms_marketing_status, $contact->email_marketing_status]);
    }

    public function test_deleting_a_contact_removes_their_signup_records(): void
    {
        $this->pendingToken('ada@example.com', ['status' => 'CONFIRMED', 'confirmed_at' => now()]);

        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame(0, DB::table('crm_pending_subscriptions')->where('email', 'ada@example.com')->count());
    }

    public function test_the_confirmation_email_only_names_an_organizer_whose_name_has_no_links(): void
    {
        $this->submit(['email' => 'first@example.com']);
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => 'Free prizes at www.example.com']);
        $this->submit(['email' => 'second@example.com']);

        Mail::assertQueued(SubscriptionConfirmationMail::class, static fn (SubscriptionConfirmationMail $mail) => $mail->hasTo('first@example.com')
            && $mail->envelope()->subject === 'Confirm your subscription to Test Organizer');
        Mail::assertQueued(SubscriptionConfirmationMail::class, static fn (SubscriptionConfirmationMail $mail) => $mail->hasTo('second@example.com')
            && $mail->envelope()->subject === 'Confirm your subscription'
            && ! str_contains($mail->render(), 'example.com'));
    }

    public function test_the_confirmation_email_names_the_installs_own_app_name_when_the_organizer_cannot_be_named(): void
    {
        config(['app.name' => 'Acme Tickets']);
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => 'Free prizes at www.example.com']);

        $this->submit(['email' => 'reader@example.com']);

        Mail::assertQueued(SubscriptionConfirmationMail::class, static function (SubscriptionConfirmationMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'an event organizer on Acme Tickets') && ! str_contains($html, 'on Hi.Events');
        });
    }

    public function test_only_a_hash_of_the_emailed_token_is_stored_and_the_link_lasts_seven_days(): void
    {
        $this->freezeSecond();
        $this->submit(['email' => 'reader@example.com'])->assertStatus(202);
        $token = $this->tokenFromMail('reader@example.com');

        $pending = DB::table('crm_pending_subscriptions')->sole();
        $this->assertSame(app(SubscriptionTokenService::class)->hash($token), $pending->token_hash);
        $this->assertNotSame($token, $pending->token_hash);
        foreach ((array) $pending as $column => $value) {
            $this->assertStringNotContainsString($token, (string) $value, $column);
        }
        $this->assertSame(now()->addDays(7)->toDateTimeString(), $pending->expires_at);

        $this->travelTo(now()->addDays(7)->subMinute());
        $this->json('GET', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'pending');

        $this->travel(2)->minutes();
        $this->json('GET', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'expired');
        $this->json('POST', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'expired');
        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'reader@example.com')->count());
    }

    #[DataProvider('formsThatDoNotTakeSignups')]
    public function test_a_signup_to_a_form_that_is_not_public_is_not_found_and_records_nothing(string $case): void
    {
        match ($case) {
            'paused' => DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['status' => 'PAUSED']),
            'deleted' => $this->crm('DELETE', "forms/{$this->signupFormId}", [], $this->ownerHeaders())->assertNoContent(),
            'account without the audience feature' => $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false),
            'unverified account' => $this->unverifyAccount(),
            'draft organizer' => $this->setOrganizerStatus($this->organizerId, 'DRAFT'),
            'archived organizer' => $this->setOrganizerStatus($this->organizerId, 'ARCHIVED'),
            'unknown form' => $this->publicId = 'sf_'.str_repeat('0', 16),
        };

        $missing = $this->json('GET', '/public/crm/forms/sf_'.str_repeat('0', 16))->assertNotFound();
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertNotFound()->assertExactJson($missing->json());
        $this->submit(['email' => 'reader@example.com'])->assertNotFound()->assertExactJson($missing->json());

        $this->assertSame(0, DB::table('crm_pending_subscriptions')->count());
        $this->assertSame(0, (int) DB::table('crm_signup_forms')->where('id', $this->signupFormId)->value('submissions_count'));
        Mail::assertNothingQueued();
    }

    public static function formsThatDoNotTakeSignups(): array
    {
        return [
            'paused' => ['paused'],
            'deleted' => ['deleted'],
            'account without the audience feature' => ['account without the audience feature'],
            'unverified account' => ['unverified account'],
            'draft organizer' => ['draft organizer'],
            'archived organizer' => ['archived organizer'],
            'unknown form' => ['unknown form'],
        ];
    }

    #[DataProvider('organizersThatCannotPublishForms')]
    public function test_the_organizer_page_form_is_hidden_until_the_account_is_verified_and_the_organizer_is_live(string $case): void
    {
        DB::table('crm_signup_forms')->where('id', $this->signupFormId)->update(['show_on_organizer_page' => true]);
        $this->json('GET', "/public/organizers/{$this->organizerId}/signup-form")->assertOk()->assertJsonPath('data.title', 'Get our news');

        $this->makeUnableToPublishForms($case);

        $this->json('GET', "/public/organizers/{$this->organizerId}/signup-form")->assertOk()->assertExactJson(['data' => null]);
    }

    #[DataProvider('organizersThatCannotPublishForms')]
    public function test_a_form_cannot_go_live_until_the_account_is_verified_and_the_organizer_is_live(string $case): void
    {
        $message = $case === 'unverified account'
            ? 'Confirm your account\'s email address before making a signup form live. Check your inbox for the confirmation email.'
            : 'Publish your organizer page before making a signup form live.';
        $paused = $this->insertSignupForm($this->organizerId, ['status' => 'PAUSED']);
        $formCount = DB::table('crm_signup_forms')->count();
        $this->makeUnableToPublishForms($case);

        $this->crm('POST', 'forms', $this->signupFormBody(), $this->ownerHeaders())->assertConflict()->assertJsonPath('message', $message);
        $this->crm('PUT', "forms/$paused", $this->signupFormBody(), $this->ownerHeaders())->assertConflict()->assertJsonPath('message', $message);

        $this->assertSame($formCount, DB::table('crm_signup_forms')->count());
        $this->assertSame('PAUSED', DB::table('crm_signup_forms')->where('id', $paused)->value('status'));

        $this->crm('POST', 'forms', $this->signupFormBody(['status' => 'PAUSED']), $this->ownerHeaders())->assertCreated()->assertJsonPath('data.status', 'PAUSED');
    }

    public static function organizersThatCannotPublishForms(): array
    {
        return [
            'unverified account' => ['unverified account'],
            'draft organizer' => ['draft organizer'],
            'archived organizer' => ['archived organizer'],
        ];
    }

    public function test_a_live_form_can_still_be_edited_and_paused_while_the_organizer_is_not_live(): void
    {
        $this->setOrganizerStatus($this->organizerId, 'DRAFT');

        $this->crm('PUT', "forms/{$this->signupFormId}", $this->signupFormBody(['title' => 'New heading']), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.title', 'New heading')
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertNotFound();

        $this->setOrganizerStatus($this->organizerId, 'LIVE');
        $this->json('GET', "/public/crm/forms/{$this->publicId}")->assertOk()->assertJsonPath('data.title', 'New heading');

        $this->setOrganizerStatus($this->organizerId, 'DRAFT');
        $this->crm('PUT', "forms/{$this->signupFormId}", $this->signupFormBody(['status' => 'PAUSED']), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'PAUSED');
    }

    public function test_a_link_sent_while_the_form_was_public_still_confirms_after_the_organizer_is_unpublished(): void
    {
        $token = $this->pendingToken('fan@example.com');
        $this->setOrganizerStatus($this->organizerId, 'DRAFT');

        $this->json('POST', "/public/crm/confirm/$token")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('SUBSCRIBED', DB::table('crm_contacts')->where('email', 'fan@example.com')->value('email_marketing_status'));
    }

    public function test_an_expired_link_only_offers_signing_up_again_while_the_form_is_public(): void
    {
        $expired = $this->pendingToken('late@example.com', ['expires_at' => now()->subMinute()]);
        $this->json('GET', "/public/crm/confirm/$expired")->assertOk()->assertJsonPath('data.form_public_id', $this->publicId);

        $this->setOrganizerStatus($this->organizerId, 'DRAFT');

        $this->json('GET', "/public/crm/confirm/$expired")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.form_public_id', null);
    }

    public function test_unconfirmed_signups_are_pruned_thirty_days_after_they_expire_and_confirmed_ones_are_kept_as_proof(): void
    {
        $this->pendingToken('long.gone@example.com', ['expires_at' => now()->subDays(31)]);
        $this->pendingToken('superseded@example.com', ['expires_at' => now()->subDays(31), 'status' => 'SUPERSEDED']);
        $this->pendingToken('recently.expired@example.com', ['expires_at' => now()->subDays(29)]);
        $this->pendingToken('waiting@example.com');
        $this->pendingToken('confirmed@example.com', ['expires_at' => now()->subDays(400), 'status' => 'CONFIRMED', 'confirmed_at' => now()->subDays(405)]);

        PruneCrmPendingSubscriptionsJob::dispatchSync();

        $this->assertSame(
            ['confirmed@example.com', 'recently.expired@example.com', 'waiting@example.com'],
            DB::table('crm_pending_subscriptions')->orderBy('email')->pluck('email')->all(),
        );
    }

    private function makeUnableToPublishForms(string $case): void
    {
        match ($case) {
            'unverified account' => $this->unverifyAccount(),
            'draft organizer' => $this->setOrganizerStatus($this->organizerId, 'DRAFT'),
            'archived organizer' => $this->setOrganizerStatus($this->organizerId, 'ARCHIVED'),
        };
    }

    private function unverifyAccount(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);
    }

    private function setOrganizerStatus(int $organizerId, string $status): void
    {
        DB::table('organizers')->where('id', $organizerId)->update(['status' => $status]);
    }

    private function formOfAnotherOrganizer(string $name): string
    {
        $organizerId = $this->createOrganizer($this->accountId, $name);
        $this->setOrganizerStatus($organizerId, 'LIVE');
        $this->trackOrganizer($this->accountId, $organizerId);

        return (string) DB::table('crm_signup_forms')->where('id', $this->insertSignupForm($organizerId))->value('public_id');
    }

    private function submit(array $body): TestResponse
    {
        return $this->json('POST', "/public/crm/forms/{$this->publicId}/submissions", $body);
    }

    private function tokenFromMail(string $email): string
    {
        $token = null;
        Mail::assertQueued(SubscriptionConfirmationMail::class, static function (SubscriptionConfirmationMail $mail) use ($email, &$token) {
            if (! $mail->hasTo($email)) {
                return false;
            }
            preg_match('#/audience/confirm/([A-Za-z0-9_-]+)#', $mail->render(), $matches);
            $token = $matches[1] ?? null;

            return $token !== null;
        });

        return (string) $token;
    }

    private function pendingToken(string $email, array $attributes = []): string
    {
        $token = app(SubscriptionTokenService::class)->generate();

        DB::table('crm_pending_subscriptions')->insert([
            'organizer_id' => $this->organizerId,
            'form_id' => $this->signupFormId,
            'email' => $email,
            'token_hash' => $token->hash,
            'status' => 'PENDING',
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            ...$attributes,
        ]);

        return $token->token;
    }
}
