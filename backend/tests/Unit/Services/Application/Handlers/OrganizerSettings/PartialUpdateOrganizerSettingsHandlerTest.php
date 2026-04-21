<?php

namespace Tests\Unit\Services\Application\Handlers\OrganizerSettings;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\PartialUpdateOrganizerSettingsDTO;
use HiEvents\Services\Application\Handlers\Organizer\Settings\PartialUpdateOrganizerSettingsHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PartialUpdateOrganizerSettingsHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerSettingsRepositoryInterface $settingsRepository;
    private OrganizerRepositoryInterface $organizerRepository;
    private PartialUpdateOrganizerSettingsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = Mockery::mock(OrganizerSettingsRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);

        $this->handler = new PartialUpdateOrganizerSettingsHandler(
            organizerSettingsRepository: $this->settingsRepository,
            organizerRepository: $this->organizerRepository,
        );
    }

    public function testTrackingPixelsArePersisted(): void
    {
        $organizer = new OrganizerDomainObject();
        $organizer->setId(1);

        $existingSettings = new OrganizerSettingDomainObject();
        $existingSettings->setId(10);
        $existingSettings->setOrganizerId(1);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => '100'])
            ->once()
            ->andReturn($organizer);

        $this->settingsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['organizer_id' => 1])
            ->once()
            ->andReturn($existingSettings);

        $trackingPixels = [
            ['provider' => 'facebook_pixel', 'pixel_id' => '1234567890', 'enabled' => true],
            ['provider' => 'google_tag_manager', 'pixel_id' => 'GTM-XXXXXXX', 'enabled' => true],
        ];

        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function (array $attributes) use ($trackingPixels) {
                    return $attributes['tracking_pixels'] === $trackingPixels
                        && $attributes['tracking_consent_acknowledged'] === true;
                }),
                Mockery::any()
            );

        $updatedSettings = new OrganizerSettingDomainObject();
        $updatedSettings->setId(10);

        $this->settingsRepository
            ->shouldReceive('findFirst')
            ->with(10)
            ->once()
            ->andReturn($updatedSettings);

        $dto = PartialUpdateOrganizerSettingsDTO::from([
            'organizer_id' => 1,
            'account_id' => '100',
            'tracking_pixels' => $trackingPixels,
            'tracking_consent_acknowledged' => true,
        ]);

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(OrganizerSettingDomainObject::class, $result);
    }

    public function testTrackingPixelsDefaultToExistingWhenNotProvided(): void
    {
        $organizer = new OrganizerDomainObject();
        $organizer->setId(1);

        $existingPixels = [
            ['provider' => 'facebook_pixel', 'pixel_id' => '9999999', 'enabled' => true],
        ];

        $existingSettings = new OrganizerSettingDomainObject();
        $existingSettings->setId(10);
        $existingSettings->setOrganizerId(1);
        $existingSettings->setTrackingPixels($existingPixels);
        $existingSettings->setTrackingConsentAcknowledged(true);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($organizer);

        $this->settingsRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($existingSettings);

        $this->settingsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function (array $attributes) use ($existingPixels) {
                    return $attributes['tracking_pixels'] === $existingPixels
                        && $attributes['tracking_consent_acknowledged'] === true;
                }),
                Mockery::any()
            );

        $this->settingsRepository
            ->shouldReceive('findFirst')
            ->with(10)
            ->once()
            ->andReturn($existingSettings);

        // Only update SEO title, not tracking pixels
        $dto = PartialUpdateOrganizerSettingsDTO::from([
            'organizer_id' => 1,
            'account_id' => '100',
            'seo_title' => 'New Title',
        ]);

        $this->handler->handle($dto);
    }
}
