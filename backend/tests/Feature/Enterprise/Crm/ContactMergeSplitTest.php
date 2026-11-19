<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetPrimaryContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SplitContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactMergeSplitTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const TARGET = 'ada@example.com';

    private const SOURCE = 'countess@example.com';

    private int $targetOrderId;

    private int $sourceOrderId;

    private int $targetId;

    private int $sourceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();

        $this->targetOrderId = $this->insertOrder(['total_gross' => 40]);
        $this->insertAttendee($this->targetOrderId);
        $this->sourceOrderId = $this->insertOrder([
            'email' => self::SOURCE,
            'first_name' => 'Augusta',
            'last_name' => 'King',
            'total_gross' => 60,
            'opted_into_marketing_at' => now()->subDays(4),
        ]);
        $this->insertCheckIn($this->insertAttendee($this->sourceOrderId));
        $this->projectOrders($this->targetOrderId, $this->sourceOrderId);

        $this->targetId = $this->contactId(self::TARGET);
        $this->sourceId = $this->contactId(self::SOURCE);
    }

    public function test_merging_moves_emails_activities_tags_notes_and_consent_to_the_target(): void
    {
        $sharedTag = $this->insertTag('VIP');
        $sourceOnlyTag = $this->insertTag('Press');
        DB::table('crm_contact_tags')->insert([
            ['contact_id' => $this->targetId, 'tag_id' => $sharedTag],
            ['contact_id' => $this->sourceId, 'tag_id' => $sharedTag],
            ['contact_id' => $this->sourceId, 'tag_id' => $sourceOnlyTag],
        ]);
        DB::table('crm_contact_notes')->insert(['contact_id' => $this->sourceId, 'organizer_id' => $this->organizerId, 'note' => 'Prefers the balcony']);

        $merged = app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        $this->assertSame($this->targetId, $merged->getId());
        $this->assertSame([self::TARGET], $this->contactEmails());
        $this->assertSame(
            [self::TARGET, self::SOURCE],
            DB::table('crm_contact_emails')->where('contact_id', $this->targetId)->orderBy('email')->pluck('email')->all(),
        );
        $this->assertSame(
            ['CHECKED_IN', 'ORDER_COMPLETED', 'ORDER_COMPLETED', 'TICKET_HELD', 'TICKET_HELD'],
            $this->activityTypes(self::TARGET),
        );
        $this->assertEqualsCanonicalizing(
            [$sharedTag, $sourceOnlyTag],
            DB::table('crm_contact_tags')->where('contact_id', $this->targetId)->pluck('tag_id')->all(),
        );
        $this->assertSame('Prefers the balcony', DB::table('crm_contact_notes')->where('contact_id', $this->targetId)->value('note'));
        $this->assertSame(self::SOURCE, DB::table('crm_consent_events')->where('contact_id', $this->targetId)->value('email'));
        foreach (['crm_contact_emails', 'crm_contact_activities', 'crm_contact_tags', 'crm_contact_notes', 'crm_consent_events', 'crm_contact_spend'] as $table) {
            $this->assertSame(0, DB::table($table)->where('contact_id', $this->sourceId)->count(), $table);
        }
    }

    public function test_merging_recomputes_the_targets_rollups_and_keeps_its_identity(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        $target = $this->contact(self::TARGET);
        $this->assertSame([2, 2, 1, 1], [(int) $target->orders_count, (int) $target->tickets_count, (int) $target->events_count, (int) $target->attended_count]);
        $this->assertEqualsWithDelta(100.0, (float) DB::table('crm_contact_spend')->where('contact_id', $this->targetId)->value('total_gross'), 0.001);
        $this->assertSame(self::TARGET, $target->email);
        $this->assertSame('ORDER', $target->source);
    }

    public function test_orders_for_a_merged_email_keep_landing_on_the_target(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        $this->projectOrders($this->sourceOrderId, $this->insertOrder(['email' => 'Countess@Example.com']));

        $this->assertSame([self::TARGET], $this->contactEmails());
        $this->assertSame(3, (int) $this->contact(self::TARGET)->orders_count);
    }

    public function test_merging_keeps_the_merged_contact_in_the_audience_of_a_campaign_that_listed_it(): void
    {
        $campaignId = $this->insertCampaign();
        DB::table('crm_campaign_audience_contacts')->insert(['campaign_id' => $campaignId, 'contact_id' => $this->sourceId]);

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        $this->assertSame(
            [$this->targetId],
            DB::table('crm_campaign_audience_contacts')->where('campaign_id', $campaignId)->pluck('contact_id')->all(),
        );
    }

    public function test_a_merge_into_a_contact_deleted_in_the_meantime_is_refused_and_keeps_the_other_contact(): void
    {
        $contacts = app(CrmContactRepositoryInterface::class);
        app(DeletedContactService::class)->delete($this->organizerId, [$this->targetId]);

        $this->assertFalse($contacts->mergeContacts($this->organizerId, $this->targetId, $this->sourceId));
        $this->assertFalse($contacts->mergeContacts($this->organizerId, $this->sourceId, $this->targetId));

        $this->assertSame([self::SOURCE], $this->contactEmails());
        $this->assertSame(1, DB::table('crm_contact_emails')->where('contact_id', $this->sourceId)->count());
        $this->assertSame(1, (int) $this->contact(self::SOURCE)->orders_count);
    }

    public function test_a_contact_cannot_be_merged_into_itself(): void
    {
        $this->expectException(ResourceConflictException::class);

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->targetId);
    }

    public function test_a_contact_of_another_organizer_cannot_be_merged_in_either_direction(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $foreignId = $this->insertContact('foreign@example.com', organizerId: $otherOrganizerId);

        foreach ([[$this->targetId, $foreignId], [$foreignId, $this->targetId]] as [$target, $source]) {
            try {
                app(MergeContactsHandler::class)->handle($this->organizerId, $target, $source);
                $this->fail('The cross-organizer merge was accepted.');
            } catch (ResourceNotFoundException) {
            }
        }

        $this->assertSame(1, DB::table('crm_contacts')->where('id', $foreignId)->where('organizer_id', $otherOrganizerId)->count());
        $this->assertSame(1, DB::table('crm_contact_emails')->where('contact_id', $foreignId)->count());
        $this->assertSame(2, DB::table('crm_contact_activities')->where('contact_id', $this->targetId)->count());
    }

    public function test_splitting_a_secondary_email_creates_a_contact_that_takes_that_emails_history(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        $tagId = $this->insertTag('VIP');
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->targetId, 'tag_id' => $tagId]);

        $remaining = app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame($this->targetId, $remaining->getId());
        $this->assertSame([self::TARGET, self::SOURCE], $this->contactEmails());

        $target = $this->contact(self::TARGET);
        $split = $this->contact(self::SOURCE);
        $this->assertNotSame($this->sourceId, (int) $split->id);
        $this->assertSame(['ORDER_COMPLETED', 'TICKET_HELD'], $this->activityTypes(self::TARGET));
        $this->assertSame(['CHECKED_IN', 'ORDER_COMPLETED', 'TICKET_HELD'], $this->activityTypes(self::SOURCE));
        $this->assertSame([1, 1, 0], [(int) $target->orders_count, (int) $target->tickets_count, (int) $target->attended_count]);
        $this->assertSame([1, 1, 1], [(int) $split->orders_count, (int) $split->tickets_count, (int) $split->attended_count]);
        $this->assertSame(['Augusta', 'King'], [$split->first_name, $split->last_name]);
        $this->assertSame('SUBSCRIBED', $split->email_marketing_status);
        $this->assertSame('NOT_SUBSCRIBED', $target->email_marketing_status);
        $this->assertEqualsWithDelta(40.0, (float) DB::table('crm_contact_spend')->where('contact_id', $target->id)->value('total_gross'), 0.001);
        $this->assertEqualsWithDelta(60.0, (float) DB::table('crm_contact_spend')->where('contact_id', $split->id)->value('total_gross'), 0.001);
        $this->assertSame([$this->targetId], DB::table('crm_contact_tags')->where('tag_id', $tagId)->pluck('contact_id')->all());
        $this->assertSame(
            [self::SOURCE],
            DB::table('crm_contact_emails')->where('contact_id', $split->id)->pluck('email')->all(),
        );
    }

    public function test_merging_and_splitting_carry_segment_automation_memberships_so_nobody_is_emailed_twice(): void
    {
        $automationId = $this->segmentAutomation();
        $otherAutomationId = $this->segmentAutomation();
        DB::table('crm_automation_memberships')->insert([
            ['automation_id' => $automationId, 'contact_id' => $this->sourceId],
            ['automation_id' => $otherAutomationId, 'contact_id' => $this->sourceId],
            ['automation_id' => $otherAutomationId, 'contact_id' => $this->targetId],
        ]);
        $members = static fn (int $id) => DB::table('crm_automation_memberships')->where('automation_id', $id)->orderBy('contact_id')->pluck('contact_id')->map(static fn ($contactId) => (int) $contactId)->all();

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        $this->assertSame([$this->targetId], $members($automationId));
        $this->assertSame([$this->targetId], $members($otherAutomationId));

        app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));
        $splitId = $this->contactId(self::SOURCE);

        $this->assertEqualsCanonicalizing([$this->targetId, $splitId], $members($automationId));
        $this->assertEqualsCanonicalizing([$this->targetId, $splitId], $members($otherAutomationId));
    }

    public function test_a_split_survives_reprojection(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->projectOrders($this->targetOrderId, $this->sourceOrderId);

        $this->assertSame(1, (int) $this->contact(self::TARGET)->orders_count);
        $this->assertSame(1, (int) $this->contact(self::SOURCE)->orders_count);
    }

    public function test_the_primary_email_cannot_be_split(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);

        try {
            app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::TARGET));
            $this->fail('The primary email was split.');
        } catch (ResourceConflictException) {
        }

        $this->assertSame([self::TARGET], $this->contactEmails());
        $this->assertSame(2, DB::table('crm_contact_emails')->where('contact_id', $this->targetId)->count());
    }

    public function test_an_email_of_another_contact_cannot_be_split_from_this_one(): void
    {
        try {
            app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));
            $this->fail('Another contact\'s email was split.');
        } catch (ResourceNotFoundException) {
        }

        $this->assertSame($this->sourceId, (int) DB::table('crm_contact_emails')->where('email', self::SOURCE)->value('contact_id'));
        $this->assertSame(2, DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_the_marketing_status_follows_the_primary_email(): void
    {
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact(self::TARGET)->email_marketing_status);

        $updated = app(SetPrimaryContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame(self::SOURCE, $updated->getEmail());
        $this->assertSame('SUBSCRIBED', $this->contact(self::SOURCE)->email_marketing_status);
        $this->assertSame($this->targetId, $this->contactId(self::SOURCE));
    }

    public function test_an_email_of_another_contact_cannot_become_the_primary(): void
    {
        try {
            app(SetPrimaryContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));
            $this->fail('Another contact\'s email became the primary.');
        } catch (ResourceNotFoundException) {
        }

        $this->assertSame(self::TARGET, DB::table('crm_contacts')->where('id', $this->targetId)->value('email'));
    }

    public function test_a_merged_in_address_bounced_by_the_same_import_as_the_target_stays_bounced_once_it_is_the_primary(): void
    {
        $this->suppressBothBySameSource('BOUNCED');

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        app(SetPrimaryContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame('BOUNCED', $this->contact(self::SOURCE)->email_marketing_status);
    }

    public function test_a_merged_in_address_complained_about_in_the_same_import_as_the_target_is_still_complained_after_a_split(): void
    {
        $this->suppressBothBySameSource('COMPLAINED');

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame('COMPLAINED', $this->contact(self::SOURCE)->email_marketing_status);
        $this->assertSame('COMPLAINED', $this->contact(self::TARGET)->email_marketing_status);
    }

    public function test_two_addresses_opted_in_by_the_same_import_both_keep_their_opt_in_through_a_merge(): void
    {
        DB::table('crm_consent_events')->where('contact_id', $this->sourceId)->delete();
        $this->suppressBothBySameSource('OPT_IN');

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        app(SetPrimaryContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame('SUBSCRIBED', $this->contact(self::SOURCE)->email_marketing_status);
    }

    public function test_a_text_opt_out_shared_by_two_contacts_with_one_number_survives_a_merge_and_a_split(): void
    {
        $phone = '+353851234567';

        foreach ([$this->targetOrderId, $this->sourceOrderId] as $orderId) {
            DB::table('orders')->where('id', $orderId)->update([
                'phone' => $phone,
                'phone_country' => 'IE',
                'sms_opted_in_at' => now()->subDays(4),
            ]);
        }
        $this->projectOrders($this->targetOrderId, $this->sourceOrderId);

        foreach ([[$this->targetId, self::TARGET], [$this->sourceId, self::SOURCE]] as [$contactId, $email]) {
            DB::table('crm_sms_consent_events')->insert([
                'organizer_id' => $this->organizerId,
                'contact_id' => $contactId,
                'email' => $email,
                'phone' => $phone,
                'action' => 'OPT_OUT',
                'source_type' => 'UNSUBSCRIBE_LINK',
                'source_id' => 55,
                'occurred_at' => now()->subDay(),
            ]);
        }

        app(MergeContactsHandler::class)->handle($this->organizerId, $this->targetId, $this->sourceId);
        app(SplitContactEmailHandler::class)->handle($this->organizerId, $this->targetId, $this->emailId(self::SOURCE));

        $this->assertSame('UNSUBSCRIBED', $this->contact(self::SOURCE)->sms_marketing_status);
        $this->assertSame('UNSUBSCRIBED', $this->contact(self::TARGET)->sms_marketing_status);
    }

    private function suppressBothBySameSource(string $action): void
    {
        foreach ([[$this->targetId, self::TARGET], [$this->sourceId, self::SOURCE]] as [$contactId, $email]) {
            DB::table('crm_consent_events')->insert([
                'organizer_id' => $this->organizerId,
                'contact_id' => $contactId,
                'email' => $email,
                'action' => $action,
                'source_type' => 'IMPORT',
                'source_id' => 77,
                'occurred_at' => now()->subDay(),
            ]);
        }
    }

    private function segmentAutomation(): int
    {
        return DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => 'SEGMENT_ENTERED',
            'is_enabled' => true,
            'enabled_at' => now()->subDay(),
            'subject' => 'Welcome to the club',
            'body' => '<p>Hello</p>',
            'preset_segment' => 'subscribers',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function emailId(string $email): int
    {
        return (int) DB::table('crm_contact_emails')
            ->where('organizer_id', $this->organizerId)
            ->where('email', $email)
            ->value('id');
    }
}
