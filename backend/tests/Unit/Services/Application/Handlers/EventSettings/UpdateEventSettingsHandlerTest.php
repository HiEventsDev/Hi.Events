<?php

namespace Tests\Unit\Services\Application\Handlers\EventSettings;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\UpdateEventSettingsHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateEventSettingsHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EventSettingsRepositoryInterface $eventSettingsRepository;
    private HtmlPurifierService $purifier;
    private DatabaseManager $databaseManager;
    private UpdateEventSettingsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->purifier->shouldReceive('purify')->andReturnUsing(fn($v) => $v);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new UpdateEventSettingsHandler(
            eventSettingsRepository: $this->eventSettingsRepository,
            purifier: $this->purifier,
            databaseManager: $this->databaseManager,
        );
    }

    public function testDispatchesCapacityEventWhenAutoProcessToggledOn(): void
    {
        Event::fake();

        $existingSettings = new EventSettingDomainObject();
        $existingSettings->setWaitlistAutoProcess(false);

        $this->eventSettingsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1])
            ->twice()
            ->andReturn($existingSettings);

        $this->eventSettingsRepository
            ->shouldReceive('updateWhere')
            ->once();

        $dto = $this->createDTO(waitlist_auto_process: true);
        $this->handler->handle($dto);

        Event::assertDispatched(CapacityChangedEvent::class, function ($event) {
            return $event->eventId === 1
                && $event->productId === null
                && $event->direction === CapacityChangeDirection::INCREASED;
        });
    }

    public function testDoesNotDispatchEventWhenAutoProcessAlreadyEnabled(): void
    {
        Event::fake();

        $existingSettings = new EventSettingDomainObject();
        $existingSettings->setWaitlistAutoProcess(true);

        $this->eventSettingsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1])
            ->twice()
            ->andReturn($existingSettings);

        $this->eventSettingsRepository
            ->shouldReceive('updateWhere')
            ->once();

        $dto = $this->createDTO(waitlist_auto_process: true);
        $this->handler->handle($dto);

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function testDoesNotDispatchEventWhenAutoProcessDisabled(): void
    {
        Event::fake();

        $existingSettings = new EventSettingDomainObject();
        $existingSettings->setWaitlistAutoProcess(true);

        $this->eventSettingsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1])
            ->twice()
            ->andReturn($existingSettings);

        $this->eventSettingsRepository
            ->shouldReceive('updateWhere')
            ->once();

        $dto = $this->createDTO(waitlist_auto_process: false);
        $this->handler->handle($dto);

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    private function createDTO(?bool $waitlist_auto_process = null): UpdateEventSettingsDTO
    {
        return UpdateEventSettingsDTO::fromArray([
            'account_id' => 1,
            'event_id' => 1,
            'post_checkout_message' => null,
            'pre_checkout_message' => null,
            'email_footer_message' => null,
            'continue_button_text' => 'Continue',
            'support_email' => 'test@test.com',
            'homepage_background_color' => '#ffffff',
            'homepage_primary_color' => '#000000',
            'homepage_primary_text_color' => '#000000',
            'homepage_secondary_color' => '#000000',
            'homepage_secondary_text_color' => '#ffffff',
            'homepage_body_background_color' => '#ffffff',
            'homepage_background_type' => 'COLOR',
            'require_attendee_details' => false,
            'attendee_details_collection_method' => 'PER_TICKET',
            'order_timeout_in_minutes' => 15,
            'website_url' => null,
            'maps_url' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'waitlist_auto_process' => $waitlist_auto_process,
            'waitlist_offer_timeout_minutes' => 60,
        ]);
    }
}
