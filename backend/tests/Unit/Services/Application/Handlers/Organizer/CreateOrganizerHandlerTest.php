<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\CreateOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use HiEvents\Services\Domain\Organizer\CreateDefaultOrganizerSettingsService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CreateOrganizerHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testDescriptionIsPurifiedOnCreate(): void
    {
        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $createDefaultOrganizerSettingsService = Mockery::mock(CreateDefaultOrganizerSettingsService::class);
        $purifier = Mockery::mock(HtmlPurifierService::class);

        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn($callback) => $callback());
        $purifier->shouldReceive('purify')->andReturnUsing(fn($v) => is_string($v) ? 'PURIFIED:' . $v : $v);

        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $organizer->shouldReceive('getId')->andReturn(5);

        $capturedAttributes = null;
        $organizerRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function ($attributes) use (&$capturedAttributes, $organizer) {
                $capturedAttributes = $attributes;
                return $organizer;
            });

        $createDefaultOrganizerSettingsService->shouldReceive('createOrganizerSettings')->once();
        $organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $organizerRepository->shouldReceive('findById')->with(5)->andReturn($organizer);

        $handler = new CreateOrganizerHandler(
            $organizerRepository,
            $databaseManager,
            $createDefaultOrganizerSettingsService,
            $purifier,
        );

        $dto = new CreateOrganizerDTO(
            name: 'Acme',
            email: 'acme@test.com',
            account_id: 1,
            timezone: 'UTC',
            currency: 'USD',
            description: '<img src=x onerror=alert(1)>',
        );

        $handler->handle($dto);

        $this->assertSame('PURIFIED:<img src=x onerror=alert(1)>', $capturedAttributes['description']);
    }
}
