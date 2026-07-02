<?php

namespace Tests\Unit\Services\Application\Handlers\EventSettings;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PartialUpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\PartialUpdateEventSettingsHandler;
use HiEvents\Services\Application\Handlers\EventSettings\UpdateEventSettingsHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PartialUpdateEventSettingsHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_explicit_show_copy_details_value_is_passed_through(): void
    {
        // Existing event has the control ON; the PATCH explicitly turns it OFF.
        $dto = $this->runPartialUpdate(
            existingValue: true,
            settings: ['allow_copy_details_to_all_attendees' => false],
        );

        $this->assertFalse($dto->allow_copy_details_to_all_attendees);
    }

    public function test_omitted_show_copy_details_key_falls_back_to_existing_value(): void
    {
        // Existing event has the control OFF; the PATCH omits the key entirely.
        // It must keep the existing value (false), NOT reset to the default (true).
        $dto = $this->runPartialUpdate(
            existingValue: false,
            settings: [],
        );

        $this->assertFalse($dto->allow_copy_details_to_all_attendees);
    }

    /**
     * Drives the partial handler and returns the UpdateEventSettingsDTO it forwards
     * to the (mocked) full handler, so we can assert how the field was resolved.
     */
    private function runPartialUpdate(bool $existingValue, array $settings): UpdateEventSettingsDTO
    {
        $existingSettings = (new EventSettingDomainObject())
            ->setAllowCopyDetailsToAllAttendees($existingValue)
            ->setPaymentProviders([]);

        $repository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $repository->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1])
            ->andReturn($existingSettings);

        $captured = null;
        $fullHandler = Mockery::mock(UpdateEventSettingsHandler::class);
        $fullHandler->shouldReceive('handle')
            ->once()
            ->andReturnUsing(function (UpdateEventSettingsDTO $dto) use (&$captured, $existingSettings) {
                $captured = $dto;
                return $existingSettings;
            });

        $handler = new PartialUpdateEventSettingsHandler($fullHandler, $repository);

        $handler->handle(new PartialUpdateEventSettingsDTO(
            account_id: 1,
            event_id: 1,
            settings: array_merge(['location_details' => []], $settings),
        ));

        return $captured;
    }
}
