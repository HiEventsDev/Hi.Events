<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\EmailTemplate\CreateEmailTemplateHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DeleteEmailTemplateHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\DeleteEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\UpdateEmailTemplateHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailTemplateSpamCheckDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.saas_mode_enabled' => true,
            'app.event_spam_check_enabled' => true,
            'ai.providers.anthropic.key' => 'test-key',
        ]);

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;
        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Template Organizer',
            'email' => 'org+'.uniqid().'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Bus::fake([EventSpamCheckJob::class]);
    }

    public function test_creating_an_event_template_rechecks_that_event(): void
    {
        $eventId = $this->insertEvent(EventStatus::LIVE->name);

        $this->app->make(CreateEmailTemplateHandler::class)->handle($this->dto(eventId: $eventId));

        $this->assertRecheckedEvents([$eventId]);
    }

    public function test_updating_an_organizer_template_rechecks_its_live_and_archived_events(): void
    {
        $liveEventId = $this->insertEvent(EventStatus::LIVE->name);
        $archivedEventId = $this->insertEvent(EventStatus::ARCHIVED->name);
        $this->insertEvent(EventStatus::DRAFT->name);
        $templateId = $this->insertTemplate(organizerId: $this->organizerId);

        $this->app->make(UpdateEmailTemplateHandler::class)->handle($this->dto(organizerId: $this->organizerId, id: $templateId));

        $this->assertRecheckedEvents([$liveEventId, $archivedEventId]);
    }

    public function test_deleting_an_event_template_rechecks_that_event(): void
    {
        $eventId = $this->insertEvent(EventStatus::ARCHIVED->name);
        $templateId = $this->insertTemplate(eventId: $eventId);

        $this->app->make(DeleteEmailTemplateHandler::class)->handle(new DeleteEmailTemplateDTO(
            id: $templateId,
            account_id: $this->accountId,
            event_id: $eventId,
        ));

        $this->assertRecheckedEvents([$eventId]);
    }

    /**
     * @param  int[]  $eventIds
     */
    private function assertRecheckedEvents(array $eventIds): void
    {
        $recheckedEventIds = Bus::dispatched(EventSpamCheckJob::class)
            ->map(fn (EventSpamCheckJob $job) => (int) $job->uniqueId())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect($eventIds)->sort()->values()->all(), $recheckedEventIds);
    }

    private function insertTemplate(?int $eventId = null, ?int $organizerId = null): int
    {
        return DB::table('email_templates')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'template_type' => EmailTemplateType::ATTENDEE_TICKET->value,
            'subject' => 'Your ticket',
            'body' => '<p>See you there</p>',
            'engine' => 'liquid',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvent(string $status): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Template Event '.uniqid(),
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'status' => $status,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dto(?int $eventId = null, ?int $organizerId = null, ?int $id = null): UpsertEmailTemplateDTO
    {
        return new UpsertEmailTemplateDTO(
            account_id: $this->accountId,
            template_type: EmailTemplateType::ATTENDEE_TICKET,
            subject: 'Your ticket',
            body: '<p>See you there</p>',
            organizer_id: $organizerId,
            event_id: $eventId,
            id: $id,
            cta: ['label' => 'View ticket', 'url_token' => 'ticket.url'],
        );
    }
}
