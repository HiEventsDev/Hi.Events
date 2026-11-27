<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Closure;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\CreateBulkOperationHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\DTO\CreateBulkOperationDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\UndoBulkOperationHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DeleteContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetContactTagsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetPrimaryContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SplitContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UpdateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportSuppressionsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\ImportContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\ImportSuppressionsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Note\CreateContactNoteHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\ConfirmSubscriptionHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\ClearBounceHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO\ClearBounceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignFeedbackService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmLockingTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const LOCK_NOT_AVAILABLE = '55P03';

    private int $adaId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmOrganizer();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $this->insertSubscribedContact('ada@example.com');
    }

    public function test_a_launch_waits_for_another_launch_of_the_same_account_before_checking_the_cap_or_moving_the_campaign(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign();

        $error = $this->whileAnotherConnectionHolds('crm_campaign_launch', $this->accountId, fn () => $this->launch($campaignId));

        $this->assertSame(self::LOCK_NOT_AVAILABLE, $error?->getCode());
        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null, 0], [$campaign->status, $campaign->launched_at, (int) $campaign->recipients_count]);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->count());
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        $this->launch($campaignId);
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_the_launch_lock_is_per_account_so_another_account_is_not_held_up(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        [, $otherAccountId] = $this->createAccountOwner();
        $this->verifyAccount($otherAccountId, ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $this->setFeatureFlagOverride($otherAccountId, FeatureFlag::CRM, true);
        $otherOrganizerId = $this->createOrganizer($otherAccountId, 'Other Organizer');
        $this->insertSubscribedContact('grace@example.com', organizerId: $otherOrganizerId);
        $otherCampaignId = $this->insertCampaign(organizerId: $otherOrganizerId, accountId: $otherAccountId);

        $error = $this->whileAnotherConnectionHolds('crm_campaign_launch', $this->accountId, fn () => $this->launch($otherCampaignId));

        $this->assertNull($error);
        $this->assertSame('SENDING', $this->campaign($otherCampaignId)->status);
    }

    public static function audienceWrites(): array
    {
        $upsert = static fn (self $test, ?int $contactId, string $email) => new UpsertContactDTO(
            account_id: $test->accountId,
            organizer_id: $test->organizerId,
            first_name: 'Augusta',
            last_name: 'King',
            email: $email,
            contact_id: $contactId,
        );
        $csv = static fn (string $content) => UploadedFile::fake()->createWithContent('contacts.csv', $content);

        return [
            'merging' => [static function (self $test): Closure {
                $sourceId = $test->insertSubscribedContact('ada.work@example.com');

                return static fn () => app(MergeContactsHandler::class)->handle($test->organizerId, $test->adaId, $sourceId);
            }],
            'splitting an address off' => [static function (self $test): Closure {
                $emailId = $test->insertSecondEmail();

                return static fn () => app(SplitContactEmailHandler::class)->handle($test->organizerId, $test->adaId, $emailId);
            }],
            'changing the primary address' => [static function (self $test): Closure {
                $emailId = $test->insertSecondEmail();

                return static fn () => app(SetPrimaryContactEmailHandler::class)->handle($test->organizerId, $test->adaId, $emailId);
            }],
            'adding a contact' => [static fn (self $test) => static fn () => app(CreateContactHandler::class)->handle($upsert($test, null, 'grace@example.com'))],
            'renaming a contact' => [static fn (self $test) => static fn () => app(UpdateContactHandler::class)->handle($upsert($test, $test->adaId, 'ada@example.com'))],
            'deleting a contact' => [static fn (self $test) => static fn () => app(DeleteContactHandler::class)->handle($test->organizerId, $test->adaId)],
            'tagging a contact' => [static function (self $test): Closure {
                $tagId = $test->insertTag('VIP');

                return static fn () => app(SetContactTagsHandler::class)->handle($test->organizerId, $test->adaId, [$tagId]);
            }],
            'adding a note' => [static fn (self $test) => static fn () => app(CreateContactNoteHandler::class)->handle($test->organizerId, $test->adaId, $test->owner->id, 'Owner', 'Called about parking')],
            'staff unsubscribing a contact' => [static function (self $test): Closure {
                $contact = app(CrmContactRepositoryInterface::class)->findById($test->adaId);

                return static fn () => app(ConsentRecorder::class)->record($contact, CrmConsentAction::OPT_OUT, CrmConsentSource::MANUAL);
            }],
            'a text opt-out' => [static fn (self $test) => static fn () => app(ConsentRecorder::class)->recordSmsConsent($test->organizerId, $test->adaId, '+353851234567', CrmConsentAction::OPT_OUT, CrmConsentSource::SMS_REPLY)],
            'unsubscribing several contacts' => [static fn (self $test) => static fn () => app(ConsentRecorder::class)->recordOptOuts($test->organizerId, [$test->adaId], $test->owner->id)],
            'reactivating a bounce' => [static function (self $test): Closure {
                $test->insertConsent($test->adaId, 'ada@example.com', 'BOUNCED', now()->subDays(40));
                $dto = new ClearBounceDTO(
                    organizer_id: $test->organizerId,
                    contact_id: $test->adaId,
                    contact_email_id: (int) DB::table('crm_contact_emails')->where('contact_id', $test->adaId)->value('id'),
                    user_id: $test->owner->id,
                );

                return static fn () => app(ClearBounceHandler::class)->handle($dto);
            }],
            'the unsubscribe link' => [static function (self $test): Closure {
                $recipientId = $test->insertRecipient($test->insertCampaign(['status' => 'SENT']), $test->adaId, 'ada@example.com', ['status' => 'SENT']);
                $token = app(CampaignUnsubscribeTokenService::class)->tokenFor($recipientId);

                return static fn () => app(CampaignUnsubscribeService::class)->unsubscribe($token);
            }],
            'opting back in from the unsubscribe link' => [static function (self $test): Closure {
                $recipientId = $test->insertRecipient($test->insertCampaign(['status' => 'SENT']), $test->adaId, 'ada@example.com', ['status' => 'SENT']);
                DB::table('crm_consent_events')->insert([
                    'organizer_id' => $test->organizerId,
                    'contact_id' => $test->adaId,
                    'email' => 'ada@example.com',
                    'action' => 'OPT_OUT',
                    'source_type' => 'UNSUBSCRIBE_LINK',
                    'source_id' => $recipientId,
                    'occurred_at' => now()->subMinute(),
                ]);
                DB::table('crm_contacts')->where('id', $test->adaId)->update(['email_marketing_status' => 'UNSUBSCRIBED']);
                $token = app(CampaignUnsubscribeTokenService::class)->tokenFor($recipientId);

                return static fn () => app(CampaignUnsubscribeService::class)->resubscribe($token);
            }],
            'a bounce reported by the mail provider' => [static function (self $test): Closure {
                $test->insertRecipient($test->insertCampaign(['status' => 'SENT']), $test->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
                $test->insertDelivery('m-ada', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);

                return static fn () => app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
            }],
            'a bounce reported after the contact was deleted' => [static function (self $test): Closure {
                $test->insertRecipientOfDeletedContact(['provider_message_id' => 'm-gone']);
                $test->insertDelivery('m-gone', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);

                return static fn () => app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
            }],
            'the unsubscribe link of a deleted contact' => [static function (self $test): Closure {
                $token = app(CampaignUnsubscribeTokenService::class)->tokenFor($test->insertRecipientOfDeletedContact());

                return static fn () => app(CampaignUnsubscribeService::class)->unsubscribe($token);
            }],
            'the opt-out link in a text to a deleted contact' => [static function (self $test): Closure {
                $token = app(CampaignUnsubscribeTokenService::class)->tokenFor($test->insertRecipientOfDeletedContact(['phone_country' => 'IE']));

                return static fn () => app(CampaignUnsubscribeService::class)->unsubscribe($token);
            }],
            'undoing a bulk tag' => [static function (self $test): Closure {
                $tagId = $test->insertTag('VIP');
                $operationId = DB::table('crm_bulk_operations')->insertGetId([
                    'account_id' => $test->accountId,
                    'organizer_id' => $test->organizerId,
                    'user_id' => $test->owner->id,
                    'action' => CrmBulkAction::ADD_TAGS->value,
                    'tag_ids' => json_encode([$tagId]),
                    'status' => 'COMPLETED',
                    'total' => 1,
                    'processed' => 1,
                    'changed' => 1,
                    'finished_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('crm_bulk_operation_contacts')->insert(['operation_id' => $operationId, 'contact_id' => $test->adaId, 'processed' => true, 'changed' => true]);
                DB::table('crm_contact_tags')->insert(['contact_id' => $test->adaId, 'tag_id' => $tagId, 'created_at' => now()]);

                return static fn () => app(UndoBulkOperationHandler::class)->handle($test->organizerId, $operationId, $test->owner->id);
            }],
            'confirming a signup' => [static function (self $test): Closure {
                $token = app(SubscriptionTokenService::class)->generate();
                DB::table('crm_pending_subscriptions')->insert([
                    'organizer_id' => $test->organizerId,
                    'email' => 'signup@example.com',
                    'token_hash' => $token->hash,
                    'status' => 'PENDING',
                    'expires_at' => now()->addDays(7),
                    'created_at' => now(),
                ]);

                return static fn () => app(ConfirmSubscriptionHandler::class)->handle($token->token);
            }],
            'a bulk tag' => [static function (self $test): Closure {
                $dto = $test->bulkOperation(CrmBulkAction::ADD_TAGS, [$test->insertTag('VIP')]);

                return static fn () => app(CreateBulkOperationHandler::class)->handle($dto);
            }],
            'a bulk unsubscribe' => [static fn (self $test) => static fn () => app(CreateBulkOperationHandler::class)->handle($test->bulkOperation(CrmBulkAction::UNSUBSCRIBE))],
            'a bulk delete' => [static fn (self $test) => static fn () => app(CreateBulkOperationHandler::class)->handle($test->bulkOperation(CrmBulkAction::DELETE))],
            'an import' => [static function (self $test) use ($csv): Closure {
                $file = $csv("Email\ngrace@example.com\n");

                return static fn () => app(ImportContactsHandler::class)->handle(new ImportContactsDTO(
                    account_id: $test->accountId,
                    organizer_id: $test->organizerId,
                    user_id: $test->owner->id,
                    file_path: $file->getRealPath(),
                    file_name: 'contacts.csv',
                    consent_attested: true,
                    tag_name: null,
                ));
            }],
            'a suppression import' => [static function (self $test) use ($csv): Closure {
                $file = $csv("Email\nada@example.com\n");

                return static fn () => app(ImportSuppressionsHandler::class)->handle(new ImportSuppressionsDTO(
                    account_id: $test->accountId,
                    organizer_id: $test->organizerId,
                    user_id: $test->owner->id,
                    file_path: $file->getRealPath(),
                    file_name: 'suppressions.csv',
                ));
            }],
        ];
    }

    #[DataProvider('audienceWrites')]
    public function test_a_write_to_the_audience_waits_while_another_write_holds_the_organizer_lock_and_changes_nothing(Closure $prepare): void
    {
        $this->adaId = $this->contactId('ada@example.com');
        $write = $prepare($this);
        $before = $this->audienceState();

        $error = $this->whileAnotherConnectionHolds('crm_projection', $this->organizerId, $write);

        $this->assertSame(self::LOCK_NOT_AVAILABLE, $error?->getCode());
        $this->assertSame($before, $this->audienceState());

        $write();
        $this->assertNotSame($before, $this->audienceState());
    }

    #[DataProvider('destructiveBulkActions')]
    public function test_a_bulk_unsubscribe_or_delete_only_starts_once_it_holds_the_organizer_lock_so_two_cannot_start_together(CrmBulkAction $action): void
    {
        $this->adaId = $this->contactId('ada@example.com');

        $error = $this->whileAnotherConnectionHolds(
            'crm_projection',
            $this->organizerId,
            fn () => app(CreateBulkOperationHandler::class)->handle($this->bulkOperation($action)),
        );

        $this->assertSame(self::LOCK_NOT_AVAILABLE, $error?->getCode());
        $this->assertSame(0, DB::table('crm_bulk_operations')->where('organizer_id', $this->organizerId)->count());
    }

    public static function destructiveBulkActions(): array
    {
        return [
            'unsubscribe' => [CrmBulkAction::UNSUBSCRIBE],
            'delete' => [CrmBulkAction::DELETE],
        ];
    }

    public function test_a_projection_job_waits_instead_of_blocking_while_another_write_holds_the_organizer_lock(): void
    {
        $orderId = $this->insertOrder(['email' => 'lin@example.com']);
        $projected = null;

        $error = $this->whileAnotherConnectionHolds('crm_projection', $this->organizerId, function () use ($orderId, &$projected): void {
            $projected = app(ContactSyncService::class)->projectOrders($this->accountId, $this->organizerId, [$orderId], waitForLock: false);
        });

        $this->assertNull($error);
        $this->assertFalse($projected);
        $this->assertNull($this->contact('lin@example.com'));
        $this->assertTrue(app(ContactSyncService::class)->projectOrders($this->accountId, $this->organizerId, [$orderId], waitForLock: false));
        $this->assertNotNull($this->contact('lin@example.com'));
    }

    private function whileAnotherConnectionHolds(string $lock, int $key, Closure $attempt): ?QueryException
    {
        config(['database.connections.crm_lock_holder' => config('database.connections.'.config('database.default'))]);
        $holder = DB::connection('crm_lock_holder');
        $holder->beginTransaction();
        $holder->select('SELECT pg_advisory_xact_lock(hashtext(?), ?)', [$lock, $key]);
        DB::statement("SET LOCAL lock_timeout = '250ms'");

        try {
            $attempt();

            return null;
        } catch (QueryException $exception) {
            return $exception;
        } finally {
            DB::statement('SET LOCAL lock_timeout = 0');
            $holder->rollBack();
            DB::purge('crm_lock_holder');
        }
    }

    /**
     * @param  int[]  $tagIds
     */
    private function bulkOperation(CrmBulkAction $action, array $tagIds = []): CreateBulkOperationDTO
    {
        return new CreateBulkOperationDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            user_id: $this->owner->id,
            action: $action,
            contact_ids: [$this->adaId],
            query: null,
            segment: new SegmentReferenceDTO,
            can_view_financials: true,
            tag_ids: $tagIds,
            confirmation_count: 1,
        );
    }

    private function insertSecondEmail(): int
    {
        return DB::table('crm_contact_emails')->insertGetId([
            'organizer_id' => $this->organizerId,
            'contact_id' => $this->adaId,
            'email' => 'ada.work@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function insertRecipientOfDeletedContact(array $attributes = []): int
    {
        $hash = app(DeletedContactHasher::class)->hash($this->organizerId, 'gone@example.com');
        DB::table('crm_deleted_contacts')->insert(['organizer_id' => $this->organizerId, 'email_hash' => $hash, 'created_at' => now()]);

        return $this->insertRecipient(
            $this->insertCampaign(['status' => 'SENT']),
            null,
            DeletedContactHasher::recipientPlaceholder($hash),
            ['status' => 'SENT', ...$attributes],
        );
    }

    private function audienceState(): array
    {
        $state = [];

        foreach (['crm_contacts', 'crm_contact_emails', 'crm_consent_events', 'crm_sms_consent_events', 'crm_contact_tags', 'crm_contact_notes', 'crm_deleted_contacts'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(static fn (object $row) => (array) $row)->all();
        }

        return $state;
    }

    private function launch(int $campaignId): void
    {
        app(CampaignLaunchService::class)->launch(app(CrmCampaignRepositoryInterface::class)->findById($campaignId));
    }
}
