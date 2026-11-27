<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Services\Domain\Account\AccountAnonymizationService;
use HiEvents\Services\Domain\Account\AccountHardDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmAccountDeletionTest extends TestCase
{
    private const KEYS = ['crm_bulk_operation_contacts' => 'operation_id', 'crm_automation_memberships' => 'automation_id', 'crm_campaign_audience_contacts' => 'campaign_id'];

    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private int $survivingAccountId;

    private int $survivingOrganizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();
        [, $this->survivingAccountId] = $this->createAccountOwner();
        $this->survivingOrganizerId = $this->createOrganizer($this->survivingAccountId, 'Surviving Organizer');
    }

    public function test_the_seed_covers_every_crm_table(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'like', 'crm\_%')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();
        $seeded = array_keys($this->seedCrmRows($this->accountId, $this->organizerId));
        sort($seeded);

        $this->assertSame($tables, $seeded);
    }

    public static function deletionPaths(): array
    {
        return [
            'anonymization' => [AccountAnonymizationService::class, 'anonymizeAccount'],
            'hard deletion' => [AccountHardDeletionService::class, 'deleteAccount'],
        ];
    }

    #[DataProvider('deletionPaths')]
    public function test_deleting_an_account_removes_all_of_its_audience_data_and_nobody_elses(string $service, string $method): void
    {
        $doomed = $this->seedCrmRows($this->accountId, $this->organizerId);
        $surviving = $this->seedCrmRows($this->survivingAccountId, $this->survivingOrganizerId);

        app($service)->{$method}($this->accountId);

        foreach ($doomed as $table => $ids) {
            $this->assertSame(0, DB::table($table)->whereIn(self::KEYS[$table] ?? 'id', $ids)->count(), "$table rows of the deleted account remain");
        }
        foreach ($surviving as $table => $ids) {
            $this->assertSame(count($ids), DB::table($table)->whereIn(self::KEYS[$table] ?? 'id', $ids)->count(), "$table rows of another account were removed");
        }
    }

    public function test_crm_counts_as_in_use_for_the_account_with_a_live_automation_and_not_for_others(): void
    {
        $usage = app(LicensedFeatureUsageService::class);
        $this->trackOrganizer($this->accountId, $this->organizerId);
        $this->assertNotContains(LicensedFeature::CRM, $usage->featuresInUse($this->accountId));

        DB::table('crm_automations')->insert([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => 'WELCOME',
            'is_enabled' => true,
            'subject' => 'Welcome',
            'body' => '<p>Hi</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $usage->forget();

        $this->assertContains(LicensedFeature::CRM, $usage->featuresInUse($this->accountId));
        $this->assertContains(LicensedFeature::CRM, $usage->featuresInUse());
        $this->assertNotContains(LicensedFeature::CRM, $usage->featuresInUse($this->survivingAccountId));
    }

    /**
     * @return array<string, int[]>
     */
    private function seedCrmRows(int $accountId, int $organizerId): array
    {
        $email = "fan-$organizerId@example.com";
        $now = now();
        $contactId = $this->insertContact($email, organizerId: $organizerId, accountId: $accountId);
        $tagId = $this->insertTag('VIP', $organizerId, $accountId);
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => $now], $organizerId, $accountId);
        $operationId = DB::table('crm_bulk_operations')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'action' => 'DELETE', 'status' => 'COMPLETED', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_bulk_operation_contacts')->insert(['operation_id' => $operationId, 'contact_id' => $contactId]);
        $formId = DB::table('crm_signup_forms')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'public_id' => 'sf_'.str_pad((string) $organizerId, 16, '0', STR_PAD_LEFT), 'name' => 'Newsletter', 'title' => 'News', 'button_label' => 'Subscribe', 'created_at' => $now, 'updated_at' => $now]);
        $automationId = DB::table('crm_automations')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'name' => 'Welcome', 'trigger' => 'WELCOME', 'subject' => 'Welcome', 'body' => '<p>Welcome</p>', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_automation_memberships')->insert(['automation_id' => $automationId, 'contact_id' => $contactId]);
        DB::table('crm_campaign_audience_contacts')->insert(['campaign_id' => $campaignId, 'contact_id' => $contactId]);
        $importId = DB::table('crm_imports')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'file_name' => 'contacts.csv', 'created_at' => $now, 'updated_at' => $now]);

        return [
            'crm_organizer_states' => [DB::table('crm_organizer_states')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now])],
            'crm_contacts' => [$contactId],
            'crm_contact_emails' => DB::table('crm_contact_emails')->where('contact_id', $contactId)->pluck('id')->all(),
            'crm_contact_spend' => [DB::table('crm_contact_spend')->insertGetId(['contact_id' => $contactId, 'organizer_id' => $organizerId, 'currency' => 'USD', 'total_gross' => 10])],
            'crm_contact_activities' => [DB::table('crm_contact_activities')->insertGetId(['organizer_id' => $organizerId, 'contact_id' => $contactId, 'type' => 'ORDER_COMPLETED', 'source_type' => 'ORDER', 'source_id' => 1, 'occurred_at' => $now])],
            'crm_tags' => [$tagId],
            'crm_contact_tags' => [DB::table('crm_contact_tags')->insertGetId(['contact_id' => $contactId, 'tag_id' => $tagId])],
            'crm_contact_notes' => [DB::table('crm_contact_notes')->insertGetId(['contact_id' => $contactId, 'organizer_id' => $organizerId, 'note' => 'Private note', 'created_at' => $now, 'updated_at' => $now])],
            'crm_consent_events' => [$this->insertConsent($contactId, $email, 'OPT_IN', $now, $organizerId)],
            'crm_sms_consent_events' => [DB::table('crm_sms_consent_events')->insertGetId(['organizer_id' => $organizerId, 'contact_id' => $contactId, 'email' => $email, 'phone' => '+14155552671', 'action' => 'OPT_IN', 'source_type' => 'ORDER', 'source_id' => 1, 'occurred_at' => $now])],
            'crm_deleted_contacts' => [DB::table('crm_deleted_contacts')->insertGetId(['organizer_id' => $organizerId, 'email_hash' => hash('sha256', "deleted-$organizerId"), 'created_at' => $now])],
            'crm_deleted_contact_salts' => [DB::table('crm_deleted_contact_salts')->insertGetId(['organizer_id' => $organizerId, 'created_at' => $now])],
            'crm_segments' => [DB::table('crm_segments')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'name' => 'Fans', 'definition' => '{"match":"all","rules":[]}', 'created_at' => $now, 'updated_at' => $now])],
            'crm_campaigns' => [$campaignId],
            'crm_campaign_recipients' => [$this->insertRecipient($campaignId, $contactId, $email, ['status' => 'SENT'], $organizerId)],
            'crm_automations' => [$automationId],
            'crm_automation_memberships' => [$automationId],
            'crm_campaign_audience_contacts' => [$campaignId],
            'crm_signup_forms' => [$formId],
            'crm_campaign_templates' => [DB::table('crm_campaign_templates')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'name' => 'Monthly', 'body' => '<p>Hi</p>', 'created_at' => $now, 'updated_at' => $now])],
            'crm_pending_subscriptions' => [DB::table('crm_pending_subscriptions')->insertGetId(['organizer_id' => $organizerId, 'form_id' => $formId, 'email' => $email, 'token_hash' => hash('sha256', "token-$organizerId"), 'expires_at' => $now->copy()->addDays(7), 'created_at' => $now])],
            'crm_imports' => [$importId],
            'crm_import_rows' => [DB::table('crm_import_rows')->insertGetId(['import_id' => $importId, 'email' => $email, 'reason' => 'UNSUBSCRIBED'])],
            'crm_bulk_operations' => [$operationId],
            'crm_bulk_operation_contacts' => [$operationId],
            'crm_sms_connections' => [DB::table('crm_sms_connections')->insertGetId(['account_id' => $accountId, 'organizer_id' => $organizerId, 'public_id' => 'smsc_'.str_pad((string) $organizerId, 24, '0', STR_PAD_LEFT), 'provider' => 'TWILIO', 'account_sid' => 'AC'.str_repeat('0', 32), 'auth_token' => encrypt('token'), 'messaging_service_sid' => 'MG'.str_pad((string) $organizerId, 32, '0', STR_PAD_LEFT), 'enabled_countries' => '["IE"]', 'created_at' => $now, 'updated_at' => $now])],
        ];
    }
}
