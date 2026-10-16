<?php

namespace Tests\Unit\Services\Application\Handlers\OrganizerSettings;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\PartialUpdateOrganizerSettingsDTO;
use HiEvents\Services\Application\Handlers\Organizer\Settings\PartialUpdateOrganizerSettingsHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Domain\Event\HomepageSectionsSanitizer;
use Mockery as m;
use Tests\TestCase;

class PartialUpdateOrganizerSettingsHomepageSectionsTest extends TestCase
{
    private OrganizerSettingsRepositoryInterface $settingsRepository;

    private HomepageSectionsSanitizer $sanitizer;

    private EventSpamCheckDispatchService $spamCheck;

    private PartialUpdateOrganizerSettingsHandler $handler;

    private array $existingSections = [['id' => 'events', 'type' => 'EVENTS', 'enabled' => true]];

    protected function setUp(): void
    {
        parent::setUp();

        $organizers = m::mock(OrganizerRepositoryInterface::class);
        $organizers->shouldReceive('findFirstWhere')->andReturn((new OrganizerDomainObject)->setId(7));

        $settings = (new OrganizerSettingDomainObject)
            ->setId(3)
            ->setOrganizerId(7)
            ->setSocialMediaHandles([])
            ->setHomepageSections($this->existingSections);

        $this->settingsRepository = m::mock(OrganizerSettingsRepositoryInterface::class);
        $this->settingsRepository->shouldReceive('findFirstWhere')->andReturn($settings);
        $this->settingsRepository->shouldReceive('findFirst')->with(3)->andReturn($settings);

        $this->sanitizer = m::mock(HomepageSectionsSanitizer::class);
        $this->spamCheck = m::mock(EventSpamCheckDispatchService::class);
        $this->spamCheck->shouldReceive('dispatchForOrganizer')->once()->with(7);

        $this->handler = new PartialUpdateOrganizerSettingsHandler(
            organizerSettingsRepository: $this->settingsRepository,
            organizerRepository: $organizers,
            eventSpamCheckDispatchService: $this->spamCheck,
            homepageSectionsSanitizer: $this->sanitizer,
        );
    }

    public function test_sanitizes_provided_sections_for_the_organizer(): void
    {
        $submitted = [['id' => 'story', 'type' => 'TEXT', 'body' => '<p>Hi</p>']];
        $clean = [['id' => 'events', 'type' => 'EVENTS', 'enabled' => true], ...$submitted];

        $this->sanitizer->shouldReceive('sanitizeForOrganizer')->once()->with($submitted, 7)->andReturn($clean);
        $this->settingsRepository->shouldReceive('updateWhere')->once()
            ->with(m::on(fn (array $attributes) => $attributes['homepage_sections'] === $clean), m::any());

        $this->handler->handle($this->dto(['homepage_sections' => $submitted]));
    }

    public function test_keeps_saved_sections_when_not_provided(): void
    {
        $this->sanitizer->shouldNotReceive('sanitizeForOrganizer');
        $this->settingsRepository->shouldReceive('updateWhere')->once()
            ->with(m::on(fn (array $attributes) => $attributes['homepage_sections'] === $this->existingSections), m::any());

        $this->handler->handle($this->dto(['seo_title' => 'Promoter']));
    }

    public function test_null_resets_to_default_layout(): void
    {
        $this->sanitizer->shouldNotReceive('sanitizeForOrganizer');
        $this->settingsRepository->shouldReceive('updateWhere')->once()
            ->with(m::on(fn (array $attributes) => $attributes['homepage_sections'] === null), m::any());

        $this->handler->handle($this->dto(['homepage_sections' => null]));
    }

    private function dto(array $input): PartialUpdateOrganizerSettingsDTO
    {
        return PartialUpdateOrganizerSettingsDTO::from(['organizer_id' => 7, 'account_id' => '1', ...$input]);
    }
}
