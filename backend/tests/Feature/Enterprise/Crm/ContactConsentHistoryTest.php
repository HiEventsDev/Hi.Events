<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactConsentHistoryTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_the_consent_history_lists_each_event_newest_first_with_its_source(): void
    {
        $importId = DB::table('crm_imports')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'user_id' => $this->owner->id,
            'file_name' => 'newsletter.csv',
            'status' => 'COMPLETED',
            'consent_attested' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $recipientId = $this->insertRecipient($this->campaignId, $this->contactId, 'alias@example.com');

        $this->insertLedgerRow('alias@example.com', 'OPT_IN', 'IMPORT', $importId, now()->subDays(6));
        $this->insertLedgerRow('alias@example.com', 'OPT_OUT', 'UNSUBSCRIBE_LINK', $recipientId, now()->subDays(2));
        $this->crm('POST', "contacts/{$this->contactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();

        $response = $this->crm('GET', "contacts/{$this->contactId}/consent", [], $this->ownerHeaders())->assertOk();

        $this->assertSame(['OPT_OUT', 'OPT_OUT', 'OPT_IN', 'OPT_IN'], array_column($response->json('data'), 'action'));
        $this->assertSame(['MANUAL', 'UNSUBSCRIBE_LINK', 'ORDER', 'IMPORT'], array_column($response->json('data'), 'source'));

        $response
            ->assertJsonPath('data.0.email', 'ada@example.com')
            ->assertJsonPath('data.0.user_name', trim($this->owner->first_name.' '.$this->owner->last_name))
            ->assertJsonPath('data.1.campaign_name', 'Spring news')
            ->assertJsonPath('data.1.user_name', null)
            ->assertJsonPath('data.2.order_id', $this->orderId)
            ->assertJsonPath('data.2.event_id', $this->eventId)
            ->assertJsonPath('data.3.import_file_name', 'newsletter.csv')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_the_consent_history_is_paginated(): void
    {
        foreach (range(1, 3) as $day) {
            $this->insertLedgerRow('ada@example.com', 'OPT_OUT', 'MANUAL', null, now()->subDays(10 + $day));
        }

        $this->crm('GET', "contacts/{$this->contactId}/consent?per_page=2&page=2", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_the_contact_lists_the_consent_state_of_each_email(): void
    {
        $this->insertLedgerRow('alias@example.com', 'OPT_IN', 'MANUAL', null, now()->subDays(3));
        $this->insertLedgerRow('alias@example.com', 'OPT_OUT', 'MANUAL', null, now()->subDays(2));

        $emails = collect($this->crm('GET', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertOk()->json('data.emails'))
            ->keyBy('email');

        $this->assertSame('OPT_IN', $emails['ada@example.com']['consent_action']);
        $this->assertSame('OPT_OUT', $emails['alias@example.com']['consent_action']);
    }

    public function test_a_complaint_outranks_a_later_opt_in_on_the_same_email(): void
    {
        $this->insertLedgerRow('alias@example.com', 'COMPLAINED', 'MANUAL', null, now()->subDays(3));
        $this->insertLedgerRow('alias@example.com', 'OPT_IN', 'MANUAL', null, now()->subDay());

        $emails = collect($this->crm('GET', "contacts/{$this->contactId}", [], $this->ownerHeaders())->json('data.emails'))->keyBy('email');

        $this->assertSame('COMPLAINED', $emails['alias@example.com']['consent_action']);
    }

    public function test_another_organizers_contact_is_not_found(): void
    {
        $this->crm('GET', "contacts/{$this->otherContactId}/consent", [], $this->ownerHeaders())->assertNotFound();
    }

    private function insertLedgerRow(string $email, string $action, string $source, ?int $sourceId, $occurredAt): void
    {
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $this->contactId,
            'email' => $email,
            'action' => $action,
            'source_type' => $source,
            'source_id' => $sourceId,
            'occurred_at' => $occurredAt,
        ]);
    }
}
