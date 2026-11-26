<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DeleteContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SplitContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UnsubscribeContactHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\SelfService\SelfServiceEditOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsConsentTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const MOBILE = '+61412345678';

    private const OTHER_MOBILE = '+61498765432';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
    }

    public function test_an_order_with_an_sms_opt_in_subscribes_the_contact_to_texts_on_that_number(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]));

        $contact = $this->contact('ada@example.com');
        $this->assertSame(self::MOBILE, $contact->phone);
        $this->assertSame('SUBSCRIBED', $contact->sms_marketing_status);
        $this->assertSame('NOT_SUBSCRIBED', $contact->email_marketing_status);
    }

    public function test_a_number_without_an_opt_in_is_stored_but_cannot_be_texted(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE]));

        $contact = $this->contact('ada@example.com');
        $this->assertSame(self::MOBILE, $contact->phone);
        $this->assertSame('NOT_SUBSCRIBED', $contact->sms_marketing_status);
    }

    public function test_consent_belongs_to_the_number_it_was_given_for(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]));
        $this->projectOrders($this->insertOrder(['phone' => self::OTHER_MOBILE, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]));

        $contact = $this->contact('ada@example.com');
        $this->assertSame(self::OTHER_MOBILE, $contact->phone);
        $this->assertSame('NOT_SUBSCRIBED', $contact->sms_marketing_status);
    }

    #[DataProvider('laterChangesToAnOlderOrder')]
    public function test_refunding_or_cancelling_an_older_order_does_not_bring_back_the_number_it_was_placed_with(array $change): void
    {
        $olderOrderId = $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(10), 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
        $this->projectOrders(
            $olderOrderId,
            $this->insertOrder(['phone' => self::OTHER_MOBILE, 'sms_opted_in_at' => now()->subDays(2), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]),
        );

        DB::table('orders')->where('id', $olderOrderId)->update([...$change, 'updated_at' => now()]);
        $this->projectOrders($olderOrderId);

        $contact = $this->contact('ada@example.com');
        $this->assertSame([self::OTHER_MOBILE, 'SUBSCRIBED'], [$contact->phone, $contact->sms_marketing_status]);
    }

    public static function laterChangesToAnOlderOrder(): array
    {
        return [
            'refunded' => [['total_refunded' => 50]],
            'cancelled' => [['status' => 'CANCELLED']],
        ];
    }

    public function test_an_abandoned_checkout_with_another_number_does_not_replace_the_number_on_record(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]));

        $this->projectOrders($this->insertOrder([
            'status' => 'RESERVED',
            'reserved_until' => now()->subMinute(),
            'phone' => self::OTHER_MOBILE,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]));

        $contact = $this->contact('ada@example.com');
        $this->assertSame([self::MOBILE, 'SUBSCRIBED'], [$contact->phone, $contact->sms_marketing_status]);
    }

    #[DataProvider('selfServiceNumberChanges')]
    public function test_a_buyer_changing_their_number_themselves_reaches_the_contact_straight_away(string $newNumber, ?string $expectedNumber): void
    {
        $orderId = $this->insertOrder(['phone' => self::MOBILE, 'phone_country' => 'AU', 'sms_opted_in_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);

        app(SelfServiceEditOrderService::class)->editOrder(
            order: app(OrderRepositoryInterface::class)->findById($orderId),
            firstName: null,
            lastName: null,
            email: null,
            ipAddress: '203.0.113.9',
            userAgent: null,
            phone: $newNumber,
        );

        $contact = $this->contact('ada@example.com');
        $this->assertSame([$expectedNumber, 'NOT_SUBSCRIBED'], [$contact->phone, $contact->sms_marketing_status]);
    }

    public static function selfServiceNumberChanges(): array
    {
        return [
            'replaced' => [self::OTHER_MOBILE, self::OTHER_MOBILE],
            'removed' => ['', null],
        ];
    }

    public function test_reprojecting_an_order_whose_opt_in_was_removed_unsubscribes_nobody_but_drops_the_consent(): void
    {
        $orderId = $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $this->projectOrders($orderId);
        $this->assertSame(1, DB::table('crm_sms_consent_events')->where('source_id', $orderId)->count());

        DB::table('orders')->where('id', $orderId)->update(['sms_opted_in_at' => null]);
        $this->projectOrders($orderId);

        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame(0, DB::table('crm_sms_consent_events')->where('source_id', $orderId)->count());
    }

    public function test_staff_can_opt_a_contact_out_of_texts_without_touching_email_consent(): void
    {
        $this->projectOrders($this->insertOrder([
            'phone' => self::MOBILE,
            'sms_opted_in_at' => now()->subDays(5),
            'opted_into_marketing_at' => now()->subDays(5),
        ]));
        $contactId = $this->contactId('ada@example.com');

        app(UnsubscribeContactHandler::class)->handle($this->organizerId, $contactId, $this->owner->id, CrmMarketingChannel::SMS);

        $contact = $this->contact('ada@example.com');
        $this->assertSame('UNSUBSCRIBED', $contact->sms_marketing_status);
        $this->assertSame('SUBSCRIBED', $contact->email_marketing_status);
    }

    public function test_an_email_opt_out_leaves_text_consent_alone(): void
    {
        $this->projectOrders($this->insertOrder([
            'phone' => self::MOBILE,
            'sms_opted_in_at' => now()->subDays(5),
            'opted_into_marketing_at' => now()->subDays(5),
        ]));

        app(UnsubscribeContactHandler::class)->handle($this->organizerId, $this->contactId('ada@example.com'), $this->owner->id);

        $contact = $this->contact('ada@example.com');
        $this->assertSame('UNSUBSCRIBED', $contact->email_marketing_status);
        $this->assertSame('SUBSCRIBED', $contact->sms_marketing_status);
    }

    public function test_reprojecting_an_old_opted_in_order_does_not_resubscribe_a_number_that_opted_out_since(): void
    {
        $orderId = $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');
        $this->optOutOfTexts($contactId);

        $this->projectOrders($orderId);

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame(
            ['OPT_IN:ORDER', 'OPT_OUT:SMS_REPLY'],
            DB::table('crm_sms_consent_events')
                ->where('contact_id', $contactId)
                ->orderBy('occurred_at')
                ->get()
                ->map(static fn (object $event) => $event->action.':'.$event->source_type)
                ->all(),
        );
    }

    public function test_a_number_opted_out_while_someone_else_held_it_stays_opted_out_for_a_contact_who_takes_it_back(): void
    {
        $this->projectOrders(
            $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(10), 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]),
            $this->insertOrder(['phone' => self::OTHER_MOBILE, 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]),
            $this->insertOrder(['email' => 'byron@example.com', 'first_name' => 'Byron', 'phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(4), 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]),
        );
        $this->assertSame(self::OTHER_MOBILE, $this->contact('ada@example.com')->phone);

        app(ConsentRecorder::class)->recordSmsOptOutForNumber($this->organizerId, self::MOBILE, CrmConsentSource::SMS_REPLY);
        $this->assertSame('UNSUBSCRIBED', $this->contact('byron@example.com')->sms_marketing_status);

        $this->travel(1)->hours();
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'created_at' => now(), 'updated_at' => now()]));

        $ada = $this->contact('ada@example.com');
        $this->assertSame(self::MOBILE, $ada->phone);
        $this->assertSame('UNSUBSCRIBED', $ada->sms_marketing_status);

        $this->travel(1)->hours();
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now(), 'created_at' => now(), 'updated_at' => now()]));

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame('UNSUBSCRIBED', $this->contact('byron@example.com')->sms_marketing_status);
    }

    public function test_opting_in_on_a_new_order_after_opting_out_subscribes_the_number_again(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]));
        $this->optOutOfTexts($this->contactId('ada@example.com'));

        $this->travel(1)->hours();
        $this->projectOrders($this->insertOrder([
            'phone' => self::MOBILE,
            'sms_opted_in_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public function test_a_deleted_contact_who_had_opted_out_of_texts_is_not_resubscribed_when_restored(): void
    {
        $orderId = $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');
        $this->optOutOfTexts($contactId);
        app(DeleteContactHandler::class)->handle($this->organizerId, $contactId);

        $restored = $this->restoreDeletedContact('ada@example.com');

        $this->assertSame(self::MOBILE, $restored->getPhone());
        $this->assertSame('UNSUBSCRIBED', $restored->getSmsMarketingStatus());

        $this->projectOrders($orderId);

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public function test_a_deleted_contact_who_was_subscribed_to_texts_is_subscribed_again_when_restored(): void
    {
        $this->projectOrders($this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]));
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->contactId('ada@example.com'));

        $this->assertSame('SUBSCRIBED', $this->restoreDeletedContact('ada@example.com')->getSmsMarketingStatus());
    }

    public function test_merging_carries_the_text_consent_to_the_kept_contact(): void
    {
        $this->projectOrders(
            $this->insertOrder(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]),
            $this->insertOrder(['email' => 'countess@example.com', 'phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(2)]),
        );

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->contactId('ada@example.com'), $this->contactId('countess@example.com'));

        $contact = $this->contact('ada@example.com');
        $this->assertSame(self::MOBILE, $contact->phone);
        $this->assertSame('SUBSCRIBED', $contact->sms_marketing_status);
    }

    public function test_splitting_an_address_off_takes_the_text_consent_given_with_it(): void
    {
        $this->projectOrders(
            $this->insertOrder(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]),
            $this->insertOrder(['email' => 'countess@example.com', 'phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(2)]),
        );
        $keptId = $this->contactId('ada@example.com');
        app(MergeContactsHandler::class)->handle($this->organizerId, $keptId, $this->contactId('countess@example.com'));
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);

        app(SplitContactEmailHandler::class)->handle(
            $this->organizerId,
            $keptId,
            (int) DB::table('crm_contact_emails')->where('organizer_id', $this->organizerId)->where('email', 'countess@example.com')->value('id'),
        );

        $split = $this->contact('countess@example.com');
        $this->assertNotSame($keptId, (int) $split->id);
        $this->assertSame([self::MOBILE, 'SUBSCRIBED'], [$split->phone, $split->sms_marketing_status]);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame([(int) $split->id], DB::table('crm_sms_consent_events')->where('organizer_id', $this->organizerId)->pluck('contact_id')->map(static fn ($id) => (int) $id)->unique()->values()->all());
    }

    public function test_text_subscribers_and_contacts_with_a_number_can_be_segmented(): void
    {
        $this->projectOrders(
            $this->insertOrder(['phone' => self::MOBILE, 'sms_opted_in_at' => now()->subDays(5)]),
            $this->insertOrder(['email' => 'grace@example.com', 'phone' => self::OTHER_MOBILE]),
            $this->insertOrder(['email' => 'nophone@example.com']),
        );

        $counts = app(SegmentCountService::class);
        $subscribed = ['match' => 'all', 'rules' => [['field' => 'sms_marketing_status', 'operator' => 'in', 'value' => ['SUBSCRIBED']]]];
        $hasPhone = ['match' => 'all', 'rules' => [['field' => 'has_phone', 'operator' => 'is_true', 'value' => null]]];

        $this->assertSame(1, $counts->count($this->organizerId, $subscribed)->contacts_count);
        $this->assertSame(2, $counts->count($this->organizerId, $hasPhone)->contacts_count);
    }

    public function test_the_consent_history_shows_text_consent_beside_email_consent(): void
    {
        $this->projectOrders($this->insertOrder([
            'phone' => self::MOBILE,
            'sms_opted_in_at' => now()->subDays(5),
            'opted_into_marketing_at' => now()->subDays(6),
        ]));

        $history = app(CrmContactRepositoryInterface::class)->findConsentHistory($this->contactId('ada@example.com'), 1, 25);

        $this->assertSame(['SMS', 'EMAIL'], collect($history->items())->pluck('channel')->all());
        $this->assertSame(self::MOBILE, $history->items()[0]->phone);
    }

    private function optOutOfTexts(int $contactId): void
    {
        app(ConsentRecorder::class)->recordSmsConsent($this->organizerId, $contactId, null, CrmConsentAction::OPT_OUT, CrmConsentSource::SMS_REPLY);
    }

    private function restoreDeletedContact(string $email): CrmContactDomainObject
    {
        return app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: $email,
            restore_deleted: true,
        ));
    }
}
