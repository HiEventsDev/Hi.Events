<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\LifecycleEmail;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Models\Account;
use HiEvents\Models\AccountUser;
use HiEvents\Models\Event;
use HiEvents\Models\Organizer;
use HiEvents\Models\Product;
use HiEvents\Models\ProductPrice;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Resources\Account\AdminAccountDetailResource;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleNudgeSweepService;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\TestCase;
use Throwable;

class LifecycleNudgeSweepServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const ENABLED_DAYS_AGO = 30;

    /** @var array<int, Email> */
    private array $sent = [];

    /** @var array<string, Throwable> */
    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');

        $this->sent = [];
        $this->failures = [];

        Mail::extend('recording', fn () => new class($this->sent, $this->failures) extends AbstractTransport
        {
            public function __construct(private array &$sent, private array &$failures)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                /** @var Email $email */
                $email = $message->getOriginalMessage();
                $to = $email->getTo()[0]->getAddress();

                if (isset($this->failures[$to])) {
                    throw $this->failures[$to];
                }

                $this->sent[] = $email;
            }

            public function __toString(): string
            {
                return 'recording';
            }
        });

        config([
            'app.saas_mode_enabled' => true,
            'mail.mailers.recording' => ['transport' => 'recording'],
            'mail.lifecycle.mailer' => 'recording',
        ]);

        foreach (LifecycleNudgeType::cases() as $type) {
            $this->enableTemplate($type);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_stale_draft_nudge_and_records_the_ledger_row(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedEvent($account, createdDaysAgo: 5, title: 'Rooftop <Sessions>');

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $email = $this->sent[0];
        $this->assertSame('Rooftop <Sessions> is waiting to be published', $email->getSubject());
        $this->assertStringContainsString('Rooftop &lt;Sessions&gt;', $email->getHtmlBody());
        $this->assertStringContainsString(sprintf('/manage/event/%d/dashboard', $event->id), $email->getHtmlBody());
        $this->assertSame('event_draft_stale', $email->getHeaders()->get('X-Tag')?->getBodyAsString());
        $userId = $account->users()->first()->id;
        $this->assertMatchesRegularExpression('#/emails/unsubscribe/'.$userId.'\.[a-f0-9]{64}#', $email->getHtmlBody());
        $this->assertMatchesRegularExpression(
            '#^<https?://.+/public/lifecycle-emails/unsubscribe/'.$userId.'\.[a-f0-9]{64}>$#',
            $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString() ?? '',
        );
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());
        $this->assertDatabaseHas('account_lifecycle_nudges', [
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::EVENT_DRAFT_STALE->name,
            'event_id' => $event->id,
        ]);
    }

    public function test_records_the_provider_message_id_on_the_nudge(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $providerMessageId = DB::table('account_lifecycle_nudges')
            ->where('account_id', $account->id)
            ->value('provider_message_id');
        $this->assertNotEmpty($providerMessageId);
    }

    public function test_sends_from_the_lifecycle_sender_address(): void
    {
        config(['mail.lifecycle.from' => ['address' => 'hello@updates.example.com', 'name' => 'Updates']]);
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame('hello@updates.example.com', $this->sent[0]->getFrom()[0]->getAddress());
    }

    public function test_sends_nothing_when_the_template_is_disabled(): void
    {
        DB::table('lifecycle_email_templates')->update(['enabled_at' => null]);
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame([], $this->sent);
        $this->assertDatabaseMissing('account_lifecycle_nudges', ['account_id' => $account->id]);
    }

    public function test_sends_nothing_without_a_lifecycle_mailer(): void
    {
        config(['mail.lifecycle.mailer' => null]);
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame([], $this->sent);
        $this->assertDatabaseMissing('account_lifecycle_nudges', ['account_id' => $account->id]);
    }

    public function test_sends_nothing_outside_saas_mode_even_with_a_mailer(): void
    {
        config(['app.saas_mode_enabled' => false]);
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_nudge_an_event_created_before_the_template_was_enabled(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: self::ENABLED_DAYS_AGO + 10);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_old_drafts_from_before_enabling_do_not_starve_newer_candidates(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $old = $this->seedAccountWithOwner("old{$i}@example.com", verified: true);
            $this->seedEvent($old, createdDaysAgo: self::ENABLED_DAYS_AGO + 10);
        }
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_nudge_someone_who_unsubscribed(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);
        $account->users()->first()->forceFill(['lifecycle_emails_unsubscribed_at' => now()])->save();

        $this->sweep();

        $this->assertSame([], $this->sent);
        $this->assertDatabaseMissing('account_lifecycle_nudges', ['account_id' => $account->id]);
    }

    public function test_does_not_send_the_same_nudge_twice(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();
        $this->sweep();

        $this->assertCount(1, $this->sent);
    }

    public function test_does_not_nudge_an_account_that_has_not_confirmed_its_email(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: false);
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_when_the_draft_is_too_new(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 1);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_when_the_account_has_a_live_event(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);
        $this->seedEvent($account, createdDaysAgo: 5, status: 'LIVE');

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_for_a_soft_deleted_event(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedEvent($account, createdDaysAgo: 5);
        DB::table('events')->where('id', $event->id)->update(['deleted_at' => now()]);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_to_an_invited_owner(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, ownerStatus: 'INVITED');
        $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_when_the_account_has_a_pending_deletion_request(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);
        DB::table('account_deletion_requests')->insert([
            'account_id' => $account->id,
            'requested_by_user_id' => $account->users()->first()->id,
            'initiated_by' => 'USER',
            'status' => 'REQUESTED',
            'expected_outcome' => 'ANONYMIZE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_a_failed_send_releases_the_claim_and_stops_the_sweep(): void
    {
        $failing = $this->seedAccountWithOwner('failing@example.com', verified: true);
        $this->seedEvent($failing, createdDaysAgo: 5);
        $later = $this->seedAccountWithOwner('later@example.com', verified: true);
        $this->seedEvent($later, createdDaysAgo: 5);
        $this->failures['failing@example.com'] = new HttpTransportException('down', $this->postmarkResponse(500, 0));

        $this->sweep();

        $this->assertSame([], $this->sent);
        $this->assertDatabaseMissing('account_lifecycle_nudges', ['account_id' => $failing->id]);

        unset($this->failures['failing@example.com']);
        $this->sweep();

        $this->assertSame(['failing@example.com', 'later@example.com'], $this->sentRecipients());
    }

    public function test_an_inactive_postmark_recipient_keeps_the_claim_and_the_sweep_continues(): void
    {
        $unsubscribed = $this->seedAccountWithOwner('unsubscribed@example.com', verified: true);
        $this->seedEvent($unsubscribed, createdDaysAgo: 5);
        $other = $this->seedAccountWithOwner('other@example.com', verified: true);
        $this->seedEvent($other, createdDaysAgo: 5);
        $this->failures['unsubscribed@example.com'] = new HttpTransportException('inactive', $this->postmarkResponse(422, 406));

        $this->sweep();
        $this->sweep();

        $this->assertSame(['other@example.com'], $this->sentRecipients());
        $this->assertDatabaseHas('account_lifecycle_nudges', ['account_id' => $unsubscribed->id]);
    }

    public function test_a_permanent_rejection_keeps_the_claim_and_does_not_block_the_sweep(): void
    {
        $rejected = $this->seedAccountWithOwner('rejected@example.com', verified: true);
        $this->seedEvent($rejected, createdDaysAgo: 5);
        $other = $this->seedAccountWithOwner('other@example.com', verified: true);
        $this->seedEvent($other, createdDaysAgo: 5);
        $this->failures['rejected@example.com'] = new HttpTransportException('invalid to', $this->postmarkResponse(422, 300));

        $this->sweep();
        $this->sweep();

        $this->assertSame(['other@example.com'], $this->sentRecipients());
        $this->assertDatabaseHas('account_lifecycle_nudges', ['account_id' => $rejected->id]);
    }

    public function test_a_template_that_does_not_render_is_skipped_without_claiming_and_other_types_still_send(): void
    {
        DB::table('lifecycle_email_templates')
            ->where('nudge_type', LifecycleNudgeType::NO_EVENT_CREATED->name)
            ->update(['subject' => '{{ 1 | divided_by: 0 }}']);
        $noEvent = $this->seedAccountWithOwner('no-event@example.com', verified: true, createdDaysAgo: 4);
        $draft = $this->seedAccountWithOwner('draft@example.com', verified: true);
        $this->seedEvent($draft, createdDaysAgo: 5);

        $this->sweep();

        $this->assertSame(['draft@example.com'], $this->sentRecipients());
        $this->assertDatabaseMissing('account_lifecycle_nudges', ['account_id' => $noEvent->id]);
    }

    public function test_sends_stripe_nudge_for_abandoned_onboarding_with_paid_products(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertStringContainsString('Nudge Organizer', $this->sent[0]->getHtmlBody());
        $this->assertStringContainsString(sprintf('/manage/organizer/%d/settings#payouts', $organizer->id), $this->sent[0]->getHtmlBody());
        $this->assertDatabaseHas('account_lifecycle_nudges', [
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::STRIPE_ONBOARDING_INCOMPLETE->name,
        ]);
    }

    public function test_does_not_nudge_stripe_onboarding_started_before_the_template_was_enabled(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false, createdHoursAgo: 24 * (self::ENABLED_DAYS_AGO + 10));
        $this->seedPaidProduct(
            $this->seedEvent($account, createdDaysAgo: self::ENABLED_DAYS_AGO + 10, organizer: $organizer),
            createdHoursAgo: 24 * (self::ENABLED_DAYS_AGO + 10),
        );

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_sends_stripe_nudge_when_a_paid_ticket_follows_an_onboarding_abandoned_before_enabling(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false, createdHoursAgo: 24 * (self::ENABLED_DAYS_AGO + 10));
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_stripe_nudge_when_another_platform_row_is_complete(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false, platform: 'ca');
        $this->seedStripePlatform($organizer, completed: true, platform: 'ie');
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 1, organizer: $organizer));

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_stripe_nudge_when_the_organizer_has_no_paid_products(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false);
        $this->seedFreeProduct($this->seedEvent($account, createdDaysAgo: 1, organizer: $organizer));

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_stripe_nudge_when_onboarding_is_too_recent(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false, createdHoursAgo: 2);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_treats_an_organizer_with_a_disconnected_platform_row_as_never_connected(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $platformId = $this->seedStripePlatform($organizer, completed: false, createdHoursAgo: 24 * (self::ENABLED_DAYS_AGO + 10));
        DB::table('organizer_stripe_platforms')->where('id', $platformId)->update(['deleted_at' => now()]);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_sends_stripe_nudge_when_paid_tickets_exist_and_stripe_was_never_started(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertSame('stripe_onboarding_incomplete', $this->sent[0]->getHeaders()->get('X-Tag')?->getBodyAsString());
        $this->assertStringContainsString(sprintf('/manage/organizer/%d/settings#payouts', $organizer->id), $this->sent[0]->getHtmlBody());
    }

    public function test_does_not_send_stripe_nudge_when_the_paid_ticket_is_too_new_and_stripe_was_never_started(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 0, organizer: $organizer), createdHoursAgo: 2);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_stripe_nudge_for_a_paid_ticket_created_before_the_template_was_enabled(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedPaidProduct(
            $this->seedEvent($account, createdDaysAgo: self::ENABLED_DAYS_AGO + 10, organizer: $organizer),
            createdHoursAgo: 24 * (self::ENABLED_DAYS_AGO + 10),
        );

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_stripe_nudge_when_the_paid_event_only_takes_offline_payments(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $event = $this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer);
        $this->seedPaymentProviders($event, ['OFFLINE']);
        $this->seedPaidProduct($event, createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_sends_stripe_nudge_when_the_paid_event_takes_stripe_and_offline_payments(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $event = $this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer);
        $this->seedPaymentProviders($event, ['STRIPE', 'OFFLINE']);
        $this->seedPaidProduct($event, createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_stripe_nudge_to_an_account_in_a_country_stripe_does_not_support(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, timezone: 'Asia/Kolkata');
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_sends_stripe_nudge_to_an_account_in_a_country_stripe_supports(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, timezone: 'Australia/Sydney');
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 2, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_an_account_matching_both_sweeps_gets_only_one_email_per_sweep(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false);
        $this->seedPaidProduct($this->seedEvent($account, createdDaysAgo: 5, organizer: $organizer), createdHoursAgo: 48);

        $this->sweep();

        $this->assertCount(1, $this->sent);
        $this->assertSame('stripe_onboarding_incomplete', $this->sent[0]->getHeaders()->get('X-Tag')?->getBodyAsString());

        $this->sweep();

        $this->assertCount(1, $this->sent);

        Carbon::setTestNow(now()->addDays(3)->addHour());
        $this->sweep();

        $this->assertCount(2, $this->sent);
        $this->assertSame('event_draft_stale', $this->sent[1]->getHeaders()->get('X-Tag')?->getBodyAsString());
    }

    public function test_waits_three_days_after_any_nudge_before_sending_another_to_the_account(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedEvent($account, createdDaysAgo: 5);
        DB::table('account_lifecycle_nudges')->insert([
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::NO_EVENT_CREATED->name,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->sweep();

        $this->assertSame([], $this->sent);

        Carbon::setTestNow(now()->addDay()->addHour());
        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_one_person_owning_two_accounts_gets_only_one_email_per_sweep(): void
    {
        $first = $this->seedAccountWithOwner('shared@example.com', verified: true);
        $this->seedEvent($first, createdDaysAgo: 5);
        $second = $this->seedAccountWithOwner('shared@example.com', verified: true, reuseUserEmail: true);
        $this->seedEvent($second, createdDaysAgo: 5);

        $this->sweep();

        $this->assertCount(1, $this->sent);
    }

    public function test_sent_nudges_appear_on_the_admin_account_resource(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedEvent($account, createdDaysAgo: 5);

        $this->sweep();

        $model = $this->app->make(AccountRepositoryInterface::class)->getAccountWithDetails($account->id);
        $payload = (new AdminAccountDetailResource($model))->toArray(request());

        $this->assertCount(1, $payload['lifecycle_nudges']);
        $this->assertSame(LifecycleNudgeType::EVENT_DRAFT_STALE->name, $payload['lifecycle_nudges'][0]['nudge_type']);
        $this->assertSame($event->id, $payload['lifecycle_nudges'][0]['event_id']);
    }

    public function test_nudges_a_confirmed_account_that_never_created_an_event(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 4);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertSame('no_event_created', $this->sent[0]->getHeaders()->get('X-Tag')?->getBodyAsString());
        $this->assertStringContainsString('/welcome', $this->sent[0]->getHtmlBody());
        $this->assertDatabaseHas('account_lifecycle_nudges', [
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::NO_EVENT_CREATED->name,
            'event_id' => null,
        ]);
    }

    public function test_does_not_nudge_a_new_account_before_three_days(): void
    {
        $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 2);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_the_no_event_nudge_to_an_unconfirmed_account(): void
    {
        $this->seedAccountWithOwner('owner@example.com', verified: false, createdDaysAgo: 4);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_a_deleted_event_still_counts_as_having_created_one(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 4);
        $event = $this->seedEvent($account, createdDaysAgo: 1);
        DB::table('events')->where('id', $event->id)->update(['deleted_at' => now()]);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_nudge_an_account_created_before_the_template_was_enabled(): void
    {
        $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: self::ENABLED_DAYS_AGO + 1);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_the_no_event_nudge_twice(): void
    {
        $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 4);

        $this->sweep();
        $this->sweep();

        $this->assertCount(1, $this->sent);
    }

    public function test_sends_live_no_sales_nudge_a_week_after_an_upcoming_paid_event_is_ready(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedReadyLiveEvent($account, readyDaysAgo: 8, title: 'Rooftop <Sessions>');

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $email = $this->sent[0];
        $this->assertSame('A few ways to get your first sale for Rooftop <Sessions>', $email->getSubject());
        $this->assertSame('live_event_no_sales', $email->getHeaders()->get('X-Tag')?->getBodyAsString());
        $this->assertStringContainsString(sprintf('/manage/event/%d/dashboard', $event->id), $email->getHtmlBody());
        $this->assertDatabaseHas('account_lifecycle_nudges', [
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::LIVE_EVENT_NO_SALES->name,
            'event_id' => $event->id,
        ]);
    }

    public function test_waits_a_week_from_when_stripe_was_connected(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: true, createdHoursAgo: 24 * 3);
        $event = $this->seedEvent($account, createdDaysAgo: 20, status: 'LIVE', organizer: $organizer);
        $this->seedPaidProduct($event);
        $this->seedOccurrence($event, startsInDays: 14);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_when_the_account_has_an_order(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedReadyLiveEvent($account, readyDaysAgo: 8);
        $this->seedOrder($this->seedEvent($account, createdDaysAgo: 60, status: 'LIVE'), 'COMPLETED');

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_when_an_offline_payment_is_awaited(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedReadyLiveEvent($account, readyDaysAgo: 8);
        $this->seedOrder($event, 'AWAITING_OFFLINE_PAYMENT');

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_for_an_event_created_before_the_template_was_enabled(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedReadyLiveEvent($account, readyDaysAgo: self::ENABLED_DAYS_AGO + 10);
        DB::table('events')->where('id', $event->id)->update(['updated_at' => now()->subDays(8)]);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_waits_a_week_from_the_last_edit_to_the_event(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $event = $this->seedReadyLiveEvent($account, readyDaysAgo: 20);
        DB::table('events')->where('id', $event->id)->update(['updated_at' => now()->subDays(2)]);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_for_an_event_that_has_already_happened(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $this->seedReadyLiveEvent($account, readyDaysAgo: 8, startsInDays: -1);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_without_a_connected_stripe_account(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, timezone: 'Asia/Kolkata');
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: false, createdHoursAgo: 24 * 8);
        $event = $this->seedEvent($account, createdDaysAgo: 8, status: 'LIVE', organizer: $organizer);
        $this->seedPaidProduct($event);
        $this->seedOccurrence($event, startsInDays: 14);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_does_not_send_live_no_sales_nudge_for_a_free_event(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true);
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: true, createdHoursAgo: 24 * 8);
        $event = $this->seedEvent($account, createdDaysAgo: 8, status: 'LIVE', organizer: $organizer);
        $this->seedFreeProduct($event);
        $this->seedOccurrence($event, startsInDays: 14);

        $this->sweep();

        $this->assertSame([], $this->sent);
    }

    public function test_sends_lapsed_payer_nudge_sixty_days_after_the_last_sold_date(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 61);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $email = $this->sent[0];
        $this->assertSame('Planning your next event?', $email->getSubject());
        $this->assertSame('lapsed_payer', $email->getHeaders()->get('X-Tag')?->getBodyAsString());
        $this->assertStringContainsString(sprintf('/manage/organizer/%d/events/ended', $event->organizer_id), $email->getHtmlBody());
        $this->assertStringNotContainsString('box office', $email->getHtmlBody());
        $this->assertDatabaseHas('account_lifecycle_nudges', [
            'account_id' => $account->id,
            'nudge_type' => LifecycleNudgeType::LAPSED_PAYER->name,
            'event_id' => $event->id,
        ]);
    }

    public function test_lapsed_payer_nudge_reaches_accounts_that_lapsed_before_the_template_was_enabled_and_mentions_new_features(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 400);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 120);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertStringContainsString('box office', $this->sent[0]->getHtmlBody());
    }

    public function test_anchors_the_lapsed_payer_nudge_on_the_latest_sold_date(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 400);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 200);
        $latest = $this->seedEventWithSoldDate($account, endedDaysAgo: 70, createdDaysAgo: 230);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertStringContainsString(sprintf('/manage/organizer/%d/events/ended', $latest->organizer_id), $this->sent[0]->getHtmlBody());
        $this->assertStringNotContainsString('box office', $this->sent[0]->getHtmlBody());
        $this->assertDatabaseHas('account_lifecycle_nudges', ['account_id' => $account->id, 'event_id' => $latest->id]);
    }

    public function test_falls_back_to_the_previous_sold_date_when_the_latest_event_was_deleted(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 400);
        $earlier = $this->seedEventWithSoldDate($account, endedDaysAgo: 200);
        $deleted = $this->seedEventWithSoldDate($account, endedDaysAgo: 100, createdDaysAgo: 230);
        DB::table('events')->where('id', $deleted->id)->update(['deleted_at' => now()->subDays(90)]);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertDatabaseHas('account_lifecycle_nudges', ['account_id' => $account->id, 'event_id' => $earlier->id]);
    }

    public function test_does_not_send_lapsed_payer_nudge_before_sixty_days(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 59);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_beyond_eighteen_months(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 800);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 560);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_an_event_was_created_recently(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        $draft = $this->seedEvent($account, createdDaysAgo: 10, organizer: Organizer::findOrFail($event->organizer_id));
        DB::table('events')->where('id', $draft->id)->update(['deleted_at' => now()]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_sends_lapsed_payer_nudge_when_a_later_draft_was_abandoned_long_ago(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 300);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 150);
        $this->seedEvent($account, createdDaysAgo: 100, organizer: Organizer::findOrFail($event->organizer_id));

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_a_date_is_upcoming(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        $this->seedOccurrence($event, startsInDays: 200);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_while_another_event_is_still_running(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 300);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        $running = $this->seedEvent($account, createdDaysAgo: 250, status: 'LIVE', organizer: $this->seedOrganizer($account));
        DB::table('event_occurrences')->insert([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $running->id,
            'start_date' => now()->subDays(200),
            'end_date' => now()->addDays(30),
            'status' => 'ACTIVE',
            'created_at' => now()->subDays(250),
            'updated_at' => now()->subDays(250),
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_a_later_date_ran_recently_without_paid_sales(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 90);
        $this->seedOccurrence($event, startsInDays: -30);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_sends_lapsed_payer_nudge_when_unsold_later_dates_ended_over_sixty_days_ago(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 400);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 200);
        $this->seedOccurrence($event, startsInDays: -100);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_the_event_still_has_a_cancelled_future_date(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        $this->seedOccurrence($event, startsInDays: 30);
        DB::table('event_occurrences')->where('event_id', $event->id)->where('start_date', '>', now())->update(['status' => 'CANCELLED']);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_the_last_sold_date_was_cancelled(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        DB::table('event_occurrences')->where('event_id', $event->id)->update(['status' => 'CANCELLED']);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_only_one_person_bought(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'friend@example.com'],
            ['email' => 'FRIEND@example.com'],
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_purchases_by_the_organizer_do_not_count_as_buyers(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'buyer@example.com'],
            ['email' => 'Owner@Example.com'],
            ['email' => 'ORGANIZER@example.com'],
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_for_fully_refunded_orders(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'a@example.com', 'total_refunded' => 25, 'refund_status' => 'REFUNDED'],
            ['email' => 'b@example.com', 'total_refunded' => 23, 'refund_status' => 'REFUNDED'],
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_partially_refunded_orders_still_count_as_paid(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'a@example.com', 'total_refunded' => 10, 'refund_status' => 'PARTIALLY_REFUNDED'],
            ['email' => 'b@example.com'],
        ]);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_for_unpaid_or_manual_orders(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'a@example.com', 'is_manually_created' => true],
            ['email' => 'b@example.com', 'payment_provider' => null],
            ['email' => 'c@example.com', 'payment_status' => 'PAYMENT_FAILED'],
            ['email' => 'd@example.com', 'status' => 'AWAITING_OFFLINE_PAYMENT', 'payment_status' => 'AWAITING_OFFLINE_PAYMENT'],
            ['email' => 'e@example.com', 'total_gross' => 0],
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_offline_payments_marked_as_received_count_as_paid(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70, orders: [
            ['email' => 'a@example.com', 'payment_provider' => 'OFFLINE'],
            ['email' => 'b@example.com', 'payment_provider' => 'OFFLINE'],
        ]);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_for_an_event_under_spam_review(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        DB::table('events')->where('id', $event->id)->update(['status' => 'PENDING_MANUAL_REVIEW']);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_the_organizer_was_deleted(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        DB::table('organizers')->where('id', $event->organizer_id)->update(['deleted_at' => now()]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_links_an_archived_event_to_the_archived_events_list(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $event = $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        DB::table('events')->where('id', $event->id)->update(['status' => 'ARCHIVED']);

        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
        $this->assertStringContainsString(sprintf('/manage/organizer/%d/events/archived', $event->organizer_id), $this->sent[0]->getHtmlBody());
    }

    public function test_does_not_send_lapsed_payer_nudge_to_an_unsubscribed_owner(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        $account->users()->first()->forceFill(['lifecycle_emails_unsubscribed_at' => now()])->save();

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_to_an_invited_owner(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, ownerStatus: 'INVITED', createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_lapsed_payer_nudge_when_the_account_has_a_pending_deletion_request(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70);
        DB::table('account_deletion_requests')->insert([
            'account_id' => $account->id,
            'requested_by_user_id' => $account->users()->first()->id,
            'initiated_by' => 'USER',
            'status' => 'REQUESTED',
            'expected_outcome' => 'ANONYMIZE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sweep();

        $this->assertSame([], $this->sentRecipients());
    }

    public function test_does_not_send_the_lapsed_payer_nudge_twice(): void
    {
        $account = $this->seedAccountWithOwner('owner@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($account, endedDaysAgo: 70);

        $this->sweep();
        Carbon::setTestNow(now()->addDays(10));
        $this->sweep();

        $this->assertSame(['owner@example.com'], $this->sentRecipients());
    }

    public function test_one_person_owning_two_lapsed_accounts_gets_the_lapsed_payer_nudge_once(): void
    {
        $first = $this->seedAccountWithOwner('shared@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($first, endedDaysAgo: 70);
        $second = $this->seedAccountWithOwner('shared@example.com', verified: true, reuseUserEmail: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($second, endedDaysAgo: 80);

        $this->sweep();
        Carbon::setTestNow(now()->addDays(10));
        $this->sweep();

        $this->assertSame(['shared@example.com'], $this->sentRecipients());
    }

    public function test_does_not_nudge_a_suspended_account(): void
    {
        $noEventAccount = $this->seedAccountWithOwner('no-event@example.com', verified: true, createdDaysAgo: 4);
        $draftAccount = $this->seedAccountWithOwner('draft@example.com', verified: true);
        $this->seedEvent($draftAccount, createdDaysAgo: 5);
        $stripeAccount = $this->seedAccountWithOwner('stripe@example.com', verified: true);
        $stripeOrganizer = $this->seedOrganizer($stripeAccount);
        $this->seedStripePlatform($stripeOrganizer, completed: false);
        $this->seedPaidProduct($this->seedEvent($stripeAccount, createdDaysAgo: 2, organizer: $stripeOrganizer), createdHoursAgo: 48);
        $liveAccount = $this->seedAccountWithOwner('live@example.com', verified: true);
        $this->seedReadyLiveEvent($liveAccount, readyDaysAgo: 8);
        $lapsedAccount = $this->seedAccountWithOwner('lapsed@example.com', verified: true, createdDaysAgo: 200);
        $this->seedEventWithSoldDate($lapsedAccount, endedDaysAgo: 70);
        $accountIds = [$noEventAccount->id, $draftAccount->id, $stripeAccount->id, $liveAccount->id, $lapsedAccount->id];

        DB::table('accounts')->whereIn('id', $accountIds)->update(['suspended_at' => now()]);
        $this->sweep();
        $this->assertSame([], $this->sentRecipients());

        DB::table('accounts')->whereIn('id', $accountIds)->update(['suspended_at' => null]);
        $this->sweep();
        $this->assertEqualsCanonicalizing(
            ['no-event@example.com', 'draft@example.com', 'stripe@example.com', 'live@example.com', 'lapsed@example.com'],
            $this->sentRecipients(),
        );
    }

    private function sweep(): void
    {
        $this->app->make(LifecycleNudgeSweepService::class)->sweep();
    }

    /**
     * @return array<int, string>
     */
    private function sentRecipients(): array
    {
        return array_map(static fn (Email $email) => $email->getTo()[0]->getAddress(), $this->sent);
    }

    private function postmarkResponse(int $status, int $errorCode): ResponseInterface
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn($status);
        $response->shouldReceive('toArray')->andReturn(['ErrorCode' => $errorCode, 'Message' => 'error']);

        return $response;
    }

    private function enableTemplate(LifecycleNudgeType $type): void
    {
        $defaults = $this->app->make(LifecycleNudgeRegistry::class)->for($type)->defaultTemplate();

        DB::table('lifecycle_email_templates')->updateOrInsert(
            ['nudge_type' => $type->name],
            [
                'subject' => $defaults->subject,
                'body' => $defaults->body,
                'cta_label' => $defaults->ctaLabel,
                'enabled_at' => now()->subDays(self::ENABLED_DAYS_AGO),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedAccountWithOwner(
        string $email,
        bool $verified,
        string $ownerStatus = 'ACTIVE',
        bool $reuseUserEmail = false,
        int $createdDaysAgo = 0,
        string $timezone = 'UTC',
    ): Account {
        $account = Account::forceCreate([
            'created_at' => now()->subDays($createdDaysAgo),
            'name' => 'Nudge Account',
            'email' => $email,
            'timezone' => $timezone,
            'currency_code' => 'USD',
            'short_id' => 'acc_'.uniqid(),
            'account_verified_at' => $verified ? now() : null,
        ]);

        $user = $reuseUserEmail
            ? User::where('email', $email)->firstOrFail()
            : User::forceCreate([
                'email' => $email,
                'first_name' => 'Owner',
                'last_name' => 'User',
                'password' => 'hash',
                'timezone' => 'UTC',
                'locale' => 'en',
            ]);

        AccountUser::forceCreate([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'status' => $ownerStatus,
            'is_account_owner' => true,
        ]);

        return $account;
    }

    private function seedOrganizer(Account $account): Organizer
    {
        return Organizer::forceCreate([
            'name' => 'Nudge Organizer',
            'account_id' => $account->id,
            'email' => 'organizer@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'LIVE',
        ]);
    }

    private function seedStripePlatform(
        Organizer $organizer,
        bool $completed,
        string $platform = 'ie',
        int $createdHoursAgo = 48,
    ): int {
        return DB::table('organizer_stripe_platforms')->insertGetId([
            'organizer_id' => $organizer->id,
            'stripe_connect_platform' => $platform,
            'stripe_account_id' => 'acct_'.uniqid(),
            'stripe_setup_completed_at' => $completed ? now() : null,
            'created_at' => now()->subHours($createdHoursAgo),
            'updated_at' => now()->subHours($createdHoursAgo),
        ]);
    }

    private function seedEvent(
        Account $account,
        int $createdDaysAgo,
        string $status = 'DRAFT',
        ?Organizer $organizer = null,
        string $title = 'Nudge Event',
    ): Event {
        $eventId = DB::table('events')->insertGetId([
            'title' => $title,
            'account_id' => $account->id,
            'organizer_id' => $organizer?->id,
            'user_id' => $account->users()->first()->id,
            'short_id' => 'evt_'.uniqid(),
            'status' => $status,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now()->subDays($createdDaysAgo),
            'updated_at' => now()->subDays($createdDaysAgo),
        ]);

        return Event::findOrFail($eventId);
    }

    private function seedPaidProduct(Event $event, int $createdHoursAgo = 0): void
    {
        $this->seedProduct($event, 'PAID', 25, $createdHoursAgo);
    }

    private function seedFreeProduct(Event $event): void
    {
        $this->seedProduct($event, 'FREE', 0);
    }

    private function seedProduct(Event $event, string $type, float $price, int $createdHoursAgo = 0): void
    {
        $product = Product::forceCreate([
            'created_at' => now()->subHours($createdHoursAgo),
            'event_id' => $event->id,
            'title' => 'Ticket',
            'type' => $type,
            'product_type' => 'TICKET',
            'order' => 1,
        ]);

        ProductPrice::forceCreate([
            'product_id' => $product->id,
            'price' => $price,
            'order' => 1,
        ]);
    }

    private function seedReadyLiveEvent(
        Account $account,
        int $readyDaysAgo,
        int $startsInDays = 14,
        string $title = 'Nudge Event',
    ): Event {
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: true, createdHoursAgo: 24 * $readyDaysAgo);
        DB::table('organizer_stripe_platforms')
            ->where('organizer_id', $organizer->id)
            ->update(['stripe_setup_completed_at' => now()->subDays($readyDaysAgo)]);
        $event = $this->seedEvent($account, createdDaysAgo: $readyDaysAgo, status: 'LIVE', organizer: $organizer, title: $title);
        $this->seedPaidProduct($event);
        $this->seedOccurrence($event, $startsInDays);

        return $event;
    }

    private function seedOccurrence(Event $event, int $startsInDays): void
    {
        DB::table('event_occurrences')->insert([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $event->id,
            'start_date' => now()->addDays($startsInDays),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     */
    private function seedEventWithSoldDate(
        Account $account,
        int $endedDaysAgo,
        ?int $createdDaysAgo = null,
        array $orders = [['email' => 'first-buyer@example.com'], ['email' => 'second-buyer@example.com']],
    ): Event {
        $organizer = $this->seedOrganizer($account);
        $this->seedStripePlatform($organizer, completed: true);
        $event = $this->seedEvent(
            $account,
            createdDaysAgo: $createdDaysAgo ?? $endedDaysAgo + 30,
            status: 'LIVE',
            organizer: $organizer,
        );
        $this->seedPaidProduct($event);
        $product = Product::where('event_id', $event->id)->firstOrFail();
        $occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $event->id,
            'start_date' => now()->subDays($endedDaysAgo)->subHours(3),
            'end_date' => now()->subDays($endedDaysAgo),
            'status' => 'ACTIVE',
            'created_at' => $event->created_at,
            'updated_at' => $event->created_at,
        ]);

        foreach ($orders as $order) {
            $orderId = DB::table('orders')->insertGetId([
                'short_id' => 'o_'.uniqid(),
                'public_id' => 'O-'.uniqid(),
                'event_id' => $event->id,
                'currency' => 'USD',
                'status' => 'COMPLETED',
                'payment_status' => 'PAYMENT_RECEIVED',
                'total_gross' => 25,
                'total_refunded' => 0,
                'total_before_additions' => 25,
                'payment_provider' => 'STRIPE',
                'is_manually_created' => false,
                'created_at' => $event->created_at,
                'updated_at' => $event->created_at,
                ...$order,
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $product->id,
                'product_price_id' => ProductPrice::where('product_id', $product->id)->value('id'),
                'event_occurrence_id' => $occurrenceId,
                'item_name' => 'Ticket',
                'quantity' => 1,
                'price' => 25,
                'total_before_additions' => 25,
                'total_gross' => 25,
            ]);
        }

        return $event;
    }

    private function seedOrder(Event $event, string $status): void
    {
        DB::table('orders')->insert([
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.uniqid(),
            'event_id' => $event->id,
            'currency' => 'USD',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $providers
     */
    private function seedPaymentProviders(Event $event, array $providers): void
    {
        DB::table('event_settings')->insert([
            'event_id' => $event->id,
            'payment_providers' => json_encode($providers),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
