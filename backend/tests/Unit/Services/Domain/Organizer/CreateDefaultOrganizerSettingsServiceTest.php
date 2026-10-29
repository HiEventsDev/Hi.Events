<?php

namespace Tests\Unit\Services\Domain\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Domain\Organizer\CreateDefaultOrganizerSettingsService;
use Mockery;
use Tests\TestCase;

class CreateDefaultOrganizerSettingsServiceTest extends TestCase
{
    public function test_new_organizer_defaults_to_per_order_collection_and_buyer_pays_fees(): void
    {
        config(['app.saas_default_pass_platform_fee_to_buyer' => true]);

        $repository = Mockery::mock(OrganizerSettingsRepositoryInterface::class);
        $repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(
                static fn (array $attributes) => $attributes['organizer_id'] === 55
                    && $attributes['default_attendee_details_collection_method'] === 'PER_ORDER'
                    && $attributes['default_pass_platform_fee_to_buyer'] === true
            ));

        $service = new CreateDefaultOrganizerSettingsService($repository);

        $service->createOrganizerSettings((new OrganizerDomainObject)->setId(55));
    }

    public function test_pass_platform_fee_default_respects_config_override(): void
    {
        config(['app.saas_default_pass_platform_fee_to_buyer' => false]);

        $repository = Mockery::mock(OrganizerSettingsRepositoryInterface::class);
        $repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(
                static fn (array $attributes) => $attributes['default_pass_platform_fee_to_buyer'] === false
            ));

        $service = new CreateDefaultOrganizerSettingsService($repository);

        $service->createOrganizerSettings((new OrganizerDomainObject)->setId(55));
    }
}
