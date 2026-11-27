<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait InteractsWithCrmApi
{
    use BuildsCrmFixtures;

    protected int $orderId;

    protected int $contactId;

    protected int $secondContactId;

    protected int $aliasEmailId;

    protected int $tagId;

    protected int $noteId;

    protected int $segmentId;

    protected int $otherOrganizerId;

    protected int $otherEventId;

    protected int $otherContactId;

    protected int $otherEmailId;

    protected int $otherTagId;

    protected int $otherNoteId;

    protected int $otherSegmentId;

    protected int $campaignId;

    protected int $otherCampaignId;

    protected int $bulkOperationId;

    protected int $automationId;

    protected int $signupFormId;

    protected int $templateId;

    protected function setUpCrmApi(): void
    {
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->setUpCrmOrganizer();

        $this->orderId = $this->insertOrder(['total_gross' => 120, 'total_refunded' => 20, 'opted_into_marketing_at' => now()->subDays(5)]);
        $this->insertAttendee($this->orderId);
        $this->projectOrders($this->orderId);

        $this->contactId = $this->contactId('ada@example.com');
        $this->secondContactId = $this->insertContact('second@example.com', ['first_name' => 'Second', 'last_name' => 'Contact']);
        $this->aliasEmailId = $this->insertEmail($this->organizerId, $this->contactId, 'alias@example.com');
        $this->tagId = $this->insertTag('VIP');
        $this->noteId = $this->insertNote($this->organizerId, $this->contactId, 'Existing note');
        $this->segmentId = $this->insertSegment($this->organizerId, 'Buyers', [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]);
        $this->campaignId = $this->insertCampaign();
        $this->bulkOperationId = $this->insertBulkOperation($this->organizerId);
        $this->signupFormId = $this->insertSignupForm($this->organizerId);
        $this->templateId = DB::table('crm_campaign_templates')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Spring template',
            'body' => '<p>Spring is here</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->automationId = DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Welcome first-timers',
            'trigger' => 'WELCOME',
            'is_enabled' => false,
            'subject' => 'Welcome',
            'body' => '<p>Welcome</p>',
            'preset_segment' => 'first_timers',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->trackOrganizer($this->accountId, $this->otherOrganizerId);
        $this->otherEventId = $this->createEvent($this->accountId, $this->otherOrganizerId, 'Other Event');
        $this->otherContactId = $this->insertContact('other@example.com', organizerId: $this->otherOrganizerId);
        $this->otherEmailId = $this->insertEmail($this->otherOrganizerId, $this->otherContactId, 'other-alias@example.com');
        $this->otherTagId = $this->insertTag('Other Tag', $this->otherOrganizerId);
        $this->otherNoteId = $this->insertNote($this->otherOrganizerId, $this->otherContactId, 'Other note');
        $this->otherSegmentId = $this->insertSegment($this->otherOrganizerId, 'Other Segment', [['field' => 'orders_count', 'operator' => 'gte', 'value' => 0]]);
        $this->otherCampaignId = $this->insertCampaign(['name' => 'Other campaign'], $this->otherOrganizerId);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array}>
     */
    protected function crmRoutes(): array
    {
        $contact = "contacts/{$this->contactId}";
        $campaign = "campaigns/{$this->campaignId}";
        $campaignBody = ['name' => 'Autumn news', 'subject' => 'Autumn dates', 'body' => '<p>New dates</p>'];
        $selection = ['mode' => 'ids', 'contact_ids' => [$this->contactId], 'confirmation_count' => 1];

        return [
            'GET crm/sync' => ['GET', 'sync', []],
            'POST crm/sync' => ['POST', 'sync', []],
            'GET crm/contacts' => ['GET', 'contacts', []],
            'GET crm/contacts/export' => ['GET', 'contacts/export', []],
            'POST crm/contacts/export' => ['POST', 'contacts/export', ['contact_ids' => [$this->contactId]]],
            'POST crm/contacts/bulk/tags' => ['POST', 'contacts/bulk/tags', [...$selection, 'action' => 'ADD_TAGS', 'tag_ids' => [$this->tagId]]],
            'POST crm/contacts/bulk/unsubscribe' => ['POST', 'contacts/bulk/unsubscribe', $selection],
            'POST crm/contacts/bulk/delete' => ['POST', 'contacts/bulk/delete', $selection],
            'GET crm/bulk-operations/{operation_id}' => ['GET', "bulk-operations/{$this->bulkOperationId}", []],
            'POST crm/bulk-operations/{operation_id}/undo' => ['POST', "bulk-operations/{$this->bulkOperationId}/undo", []],
            'GET crm/forms' => ['GET', 'forms', []],
            'POST crm/forms' => ['POST', 'forms', $this->signupFormBody()],
            'PUT crm/forms/{form_id}' => ['PUT', "forms/{$this->signupFormId}", $this->signupFormBody()],
            'DELETE crm/forms/{form_id}' => ['DELETE', "forms/{$this->signupFormId}", []],
            'GET crm/imports' => ['GET', 'imports', []],
            'POST crm/imports' => ['POST', 'imports', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', "Email\nimported@example.com\n")]],
            'POST crm/imports/suppressions/preview' => ['POST', 'imports/suppressions/preview', ['file' => UploadedFile::fake()->createWithContent('suppressions.csv', "Email\nsuppressed@example.com\n")]],
            'GET crm/suppressions/summary' => ['GET', 'suppressions/summary', []],
            'POST crm/suppressions/lookup' => ['POST', 'suppressions/lookup', ['emails' => ['ada@example.com']]],
            'POST crm/contacts/{contact_id}/emails/{contact_email_id}/clear-bounce' => ['POST', "$contact/emails/{$this->aliasEmailId}/clear-bounce", []],
            'POST crm/contacts' => ['POST', 'contacts', ['email' => 'new@example.com', 'first_name' => 'New']],
            'GET crm/contacts/{contact_id}' => ['GET', $contact, []],
            'PUT crm/contacts/{contact_id}' => ['PUT', $contact, ['first_name' => 'Renamed', 'last_name' => 'Person']],
            'DELETE crm/contacts/{contact_id}' => ['DELETE', $contact, []],
            'POST crm/contacts/{contact_id}/merge' => ['POST', "$contact/merge", ['source_contact_id' => $this->secondContactId]],
            'POST crm/contacts/{contact_id}/emails/{contact_email_id}/split' => ['POST', "$contact/emails/{$this->aliasEmailId}/split", []],
            'POST crm/contacts/{contact_id}/emails/{contact_email_id}/primary' => ['POST', "$contact/emails/{$this->aliasEmailId}/primary", []],
            'PUT crm/contacts/{contact_id}/tags' => ['PUT', "$contact/tags", ['tag_ids' => [$this->tagId]]],
            'POST crm/contacts/{contact_id}/unsubscribe' => ['POST', "$contact/unsubscribe", []],
            'GET crm/contacts/{contact_id}/activities' => ['GET', "$contact/activities", []],
            'GET crm/contacts/{contact_id}/consent' => ['GET', "$contact/consent", []],
            'GET crm/contacts/{contact_id}/notes' => ['GET', "$contact/notes", []],
            'POST crm/contacts/{contact_id}/notes' => ['POST', "$contact/notes", ['note' => 'A new note']],
            'DELETE crm/contacts/{contact_id}/notes/{note_id}' => ['DELETE', "$contact/notes/{$this->noteId}", []],
            'GET crm/tags' => ['GET', 'tags', []],
            'POST crm/tags' => ['POST', 'tags', ['name' => 'Press']],
            'PUT crm/tags/{tag_id}' => ['PUT', "tags/{$this->tagId}", ['name' => 'Very Important']],
            'DELETE crm/tags/{tag_id}' => ['DELETE', "tags/{$this->tagId}", []],
            'GET crm/segments' => ['GET', 'segments', []],
            'GET crm/segments/count' => ['GET', 'segments/count?preset=repeat_buyers', []],
            'GET crm/segment-options' => ['GET', 'segment-options', []],
            'POST crm/segments' => ['POST', 'segments', ['name' => 'Fans', 'definition' => ['match' => 'all', 'rules' => [['field' => 'events_count', 'operator' => 'gte', 'value' => 2]]]]],
            'PUT crm/segments/{segment_id}' => ['PUT', "segments/{$this->segmentId}", ['name' => 'Renamed', 'definition' => ['match' => 'all', 'rules' => [['field' => 'events_count', 'operator' => 'gte', 'value' => 3]]]]],
            'DELETE crm/segments/{segment_id}' => ['DELETE', "segments/{$this->segmentId}", []],
            'GET crm/insights/overview' => ['GET', 'insights/overview', []],
            'GET crm/insights/retention' => ['GET', 'insights/retention', []],
            'GET crm/insights/lead-time' => ['GET', 'insights/lead-time', []],
            'GET crm/insights/events/{event_id}' => ['GET', "insights/events/{$this->eventId}", []],
            'GET crm/campaigns' => ['GET', 'campaigns', []],
            'POST crm/campaigns' => ['POST', 'campaigns', $campaignBody],
            'GET crm/campaigns/{campaign_id}' => ['GET', $campaign, []],
            'PUT crm/campaigns/{campaign_id}' => ['PUT', $campaign, $campaignBody],
            'DELETE crm/campaigns/{campaign_id}' => ['DELETE', $campaign, []],
            'GET crm/campaigns/{campaign_id}/preview' => ['GET', "$campaign/preview", []],
            'GET crm/campaigns/{campaign_id}/send-check' => ['GET', "$campaign/send-check", []],
            'POST crm/campaigns/{campaign_id}/send' => ['POST', "$campaign/send", []],
            'POST crm/campaigns/{campaign_id}/cancel' => ['POST', "$campaign/cancel", []],
            'POST crm/campaigns/{campaign_id}/duplicate' => ['POST', "$campaign/duplicate", []],
            'POST crm/campaigns/{campaign_id}/end-early-access' => ['POST', "$campaign/end-early-access", []],
            'GET crm/templates' => ['GET', 'templates', []],
            'POST crm/templates' => ['POST', 'templates', ['name' => 'Monthly news', 'subject' => 'News', 'body' => '<p>Hi</p>']],
            'PUT crm/templates/{template_id}' => ['PUT', "templates/{$this->templateId}", ['name' => 'Renamed template', 'body' => '<p>Hello</p>']],
            'DELETE crm/templates/{template_id}' => ['DELETE', "templates/{$this->templateId}", []],
            'POST crm/campaigns/{campaign_id}/test' => ['POST', "$campaign/test", []],
            'POST crm/campaigns/preview' => ['POST', 'campaigns/preview', ['subject' => 'Autumn dates', 'body' => '<p>New dates</p>']],
            'POST crm/campaigns/sms-preview' => ['POST', 'campaigns/sms-preview', ['body' => 'Tickets are on sale']],
            'GET crm/campaigns/{campaign_id}/recipients' => ['GET', "$campaign/recipients", []],
            'GET crm/automations' => ['GET', 'automations', []],
            'GET crm/automations/templates' => ['GET', 'automations/templates', []],
            'POST crm/automations' => ['POST', 'automations', ['name' => 'Welcome', 'trigger' => 'WELCOME', 'subject' => 'Welcome', 'body' => '<p>Welcome</p>']],
            'PUT crm/automations/{automation_id}' => ['PUT', "automations/{$this->automationId}", ['name' => 'Welcome', 'subject' => 'Welcome', 'body' => '<p>Welcome</p>']],
            'POST crm/automations/{automation_id}/enable' => ['POST', "automations/{$this->automationId}/enable", []],
            'POST crm/automations/{automation_id}/disable' => ['POST', "automations/{$this->automationId}/disable", []],
            'DELETE crm/automations/{automation_id}' => ['DELETE', "automations/{$this->automationId}", []],
            'POST crm/automations/preview' => ['POST', 'automations/preview', ['trigger' => 'WELCOME', 'subject' => 'Welcome', 'body' => '<p>Welcome</p>']],
            'POST crm/automations/test' => ['POST', 'automations/test', ['trigger' => 'WELCOME', 'subject' => 'Welcome', 'body' => '<p>Welcome</p>']],
            'GET crm/sms-connection' => ['GET', 'sms-connection', []],
            'POST crm/sms-connection/senders' => ['POST', 'sms-connection/senders', ['account_sid' => 'AC'.str_repeat('0', 32), 'auth_token' => 'test-token']],
            'PUT crm/sms-connection' => ['PUT', 'sms-connection', ['account_sid' => 'AC'.str_repeat('0', 32), 'auth_token' => 'test-token', 'messaging_service_sid' => 'MG'.str_repeat('0', 32), 'enabled_countries' => ['IE']]],
            'POST crm/sms-connection/test' => ['POST', 'sms-connection/test', ['phone' => '+353851234567']],
            'DELETE crm/sms-connection' => ['DELETE', 'sms-connection', []],
        ];
    }

    /**
     * @return string[]
     */
    protected function registeredCrmRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(static fn (Route $route) => str_starts_with($route->uri(), 'organizers/{organizer_id}/crm/'))
            ->map(static fn (Route $route) => implode('|', array_diff($route->methods(), ['HEAD'])).' '.substr($route->uri(), strlen('organizers/{organizer_id}/')))
            ->sort()
            ->values()
            ->all();
    }

    protected function crm(string $method, string $path, array $body, array $headers, ?int $organizerId = null): TestResponse
    {
        return $this->json($method, '/organizers/'.($organizerId ?? $this->organizerId).'/crm/'.$path, $body, $headers);
    }

    protected function ownerHeaders(): array
    {
        return $this->headersFor($this->owner, $this->accountId);
    }

    protected function memberHeaders(User $member): array
    {
        return $this->headersFor($member, $this->accountId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function crmState(): array
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'like', 'crm\_%')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $state = [];
        foreach ([...$tables, 'promo_codes'] as $table) {
            $state[$table] = DB::table($table)
                ->get()
                ->map(static fn (object $row) => (array) $row)
                ->sortBy(static fn (array $row) => json_encode($row))
                ->values()
                ->all();
        }

        return $state;
    }

    protected function insertEmail(int $organizerId, int $contactId, string $email): int
    {
        return DB::table('crm_contact_emails')->insertGetId([
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function insertNote(int $organizerId, int $contactId, string $note): int
    {
        return DB::table('crm_contact_notes')->insertGetId([
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function signupFormBody(array $overrides = []): array
    {
        return [
            'name' => 'Newsletter',
            'status' => 'ACTIVE',
            'title' => 'Get our news',
            'description' => 'Be first to hear about new dates.',
            'button_label' => 'Subscribe',
            'success_message' => null,
            'asks_for_name' => false,
            'privacy_url' => null,
            'tag_ids' => [],
            'show_on_organizer_page' => false,
            ...$overrides,
        ];
    }

    protected function insertSignupForm(int $organizerId, array $attributes = []): int
    {
        return DB::table('crm_signup_forms')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'public_id' => 'sf_'.strtolower(Str::random(16)),
            'name' => 'Newsletter',
            'status' => 'ACTIVE',
            'title' => 'Get our news',
            'button_label' => 'Subscribe',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    protected function insertBulkOperation(int $organizerId, array $attributes = []): int
    {
        return DB::table('crm_bulk_operations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'user_id' => $this->owner->id,
            'action' => 'ADD_TAGS',
            'tag_ids' => json_encode([$this->tagId]),
            'status' => 'COMPLETED',
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    protected function insertSegment(int $organizerId, string $name, array $rules): int
    {
        return DB::table('crm_segments')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'name' => $name,
            'definition' => json_encode(['match' => 'all', 'rules' => $rules]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
