<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\EditOrganizerDTO;
use HiEvents\Services\Application\Handlers\Organizer\EditOrganizerHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class EditOrganizerHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_first_day_of_week_is_updated_when_provided(): void
    {
        $attributes = $this->editOrganizer(new EditOrganizerDTO(
            id: 5,
            name: 'Acme',
            email: 'acme@test.com',
            account_id: 1,
            timezone: 'UTC',
            currency: 'USD',
            first_day_of_week: 0,
        ));

        $this->assertSame(0, $attributes['first_day_of_week']);
    }

    public function test_first_day_of_week_is_left_unchanged_when_omitted(): void
    {
        $attributes = $this->editOrganizer(new EditOrganizerDTO(
            id: 5,
            name: 'Acme',
            email: 'acme@test.com',
            account_id: 1,
            timezone: 'UTC',
            currency: 'USD',
        ));

        $this->assertArrayNotHasKey('first_day_of_week', $attributes);
        $this->assertSame('UTC', $attributes['timezone']);
    }

    private function editOrganizer(EditOrganizerDTO $dto): array
    {
        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $purifier = Mockery::mock(HtmlPurifierService::class);

        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
        $purifier->shouldReceive('purify')->andReturnUsing(fn ($v) => $v);

        $capturedAttributes = null;
        $organizerRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturnUsing(function ($attributes, $where) use (&$capturedAttributes) {
                $capturedAttributes = $attributes;

                return 1;
            });

        $organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 5, 'account_id' => 1])
            ->andReturn(Mockery::mock(OrganizerDomainObject::class));

        $handler = new EditOrganizerHandler($organizerRepository, $databaseManager, $purifier);

        $handler->handle($dto);

        return $capturedAttributes;
    }
}
