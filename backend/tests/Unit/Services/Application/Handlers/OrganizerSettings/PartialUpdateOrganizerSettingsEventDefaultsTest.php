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
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartialUpdateOrganizerSettingsEventDefaultsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerSettingsRepositoryInterface $settingsRepository;

    private OrganizerRepositoryInterface $organizerRepository;

    private PartialUpdateOrganizerSettingsHandler $handler;

    private ?array $savedAttributes = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = Mockery::mock(OrganizerSettingsRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);

        $this->handler = new PartialUpdateOrganizerSettingsHandler(
            organizerSettingsRepository: $this->settingsRepository,
            organizerRepository: $this->organizerRepository,
            eventSpamCheckDispatchService: Mockery::mock(EventSpamCheckDispatchService::class)->shouldIgnoreMissing(),
            homepageSectionsSanitizer: Mockery::mock(HomepageSectionsSanitizer::class),
        );
    }

    public function test_turning_attendee_self_service_off_is_saved(): void
    {
        $this->handle(new OrganizerSettingDomainObject, ['default_allow_attendee_self_edit' => false]);

        $this->assertArrayHasKey('default_allow_attendee_self_edit', $this->savedAttributes);
        $this->assertFalse($this->savedAttributes['default_allow_attendee_self_edit']);
    }

    public function test_turning_attendee_self_service_back_on_is_saved(): void
    {
        $existing = (new OrganizerSettingDomainObject)->setDefaultAllowAttendeeSelfEdit(false);

        $this->handle($existing, ['default_allow_attendee_self_edit' => true]);

        $this->assertTrue($this->savedAttributes['default_allow_attendee_self_edit']);
    }

    public static function untouchedInputs(): array
    {
        return [
            'omitted' => [[]],
            'explicit null' => [[
                'default_allow_attendee_self_edit' => null,
                'default_show_marketing_opt_in' => null,
                'default_pass_platform_fee_to_buyer' => null,
            ]],
        ];
    }

    #[DataProvider('untouchedInputs')]
    public function test_event_default_switches_keep_their_saved_value_when_not_set(array $input): void
    {
        $existing = (new OrganizerSettingDomainObject)
            ->setDefaultAllowAttendeeSelfEdit(false)
            ->setDefaultShowMarketingOptIn(true)
            ->setDefaultPassPlatformFeeToBuyer(true);

        $this->handle($existing, $input);

        $this->assertFalse($this->savedAttributes['default_allow_attendee_self_edit']);
        $this->assertTrue($this->savedAttributes['default_show_marketing_opt_in']);
        $this->assertTrue($this->savedAttributes['default_pass_platform_fee_to_buyer']);
    }

    private function handle(OrganizerSettingDomainObject $existing, array $input): void
    {
        $organizer = (new OrganizerDomainObject)->setId(1);
        $existing->setId(10)->setOrganizerId(1);

        $this->organizerRepository->shouldReceive('findFirstWhere')->once()->andReturn($organizer);
        $this->settingsRepository->shouldReceive('findFirstWhere')->once()->andReturn($existing);
        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(Mockery::on(function (array $attributes) {
                $this->savedAttributes = $attributes;

                return true;
            }), ['organizer_id' => 1, 'id' => 10]);
        $this->settingsRepository->shouldReceive('findFirst')->with(10)->once()->andReturn($existing);

        $this->handler->handle(PartialUpdateOrganizerSettingsDTO::from([
            'organizer_id' => 1,
            'account_id' => 100,
            ...$input,
        ]));
    }
}
