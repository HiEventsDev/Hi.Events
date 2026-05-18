<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DataTransferObjects\AttributesDTO;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Events\Dispatcher;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateEventHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EventRepositoryInterface $eventRepository;
    private Dispatcher $dispatcher;
    private DatabaseManager $databaseManager;
    private OrderRepositoryInterface $orderRepository;
    private HtmlPurifierService $purifier;
    private UpdateEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->dispatcher = m::mock(Dispatcher::class);
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->purifier = m::mock(HtmlPurifierService::class);

        $this->purifier
            ->shouldReceive('purify')
            ->andReturnUsing(fn($value) => $value);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->dispatcher
            ->shouldReceive('dispatchEvent')
            ->byDefault();

        $this->handler = new UpdateEventHandler(
            eventRepository: $this->eventRepository,
            dispatcher: $this->dispatcher,
            databaseManager: $this->databaseManager,
            orderRepository: $this->orderRepository,
            purifier: $this->purifier,
        );
    }

    public function testPersistsAttributesWhenUpdatingEvent(): void
    {
        $existingEvent = (new EventDomainObject())
            ->setId(1)
            ->setAccountId(10)
            ->setTitle('Original Title')
            ->setTimezone('UTC')
            ->setCurrency('USD')
            ->setCategory('OTHER')
            ->setAttributes([]);

        $updatedEvent = (new EventDomainObject())
            ->setId(1)
            ->setAccountId(10)
            ->setTitle('Updated Title')
            ->setTimezone('UTC')
            ->setCurrency('USD')
            ->setAttributes([
                ['name' => 'venue_type', 'value' => 'warehouse', 'is_public' => true],
            ]);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 10])
            ->twice()
            ->andReturn($existingEvent, $updatedEvent);

        $newAttributes = new Collection([
            new AttributesDTO(name: 'venue_type', value: 'warehouse', is_public: true),
        ]);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes, array $where) {
                $this->assertArrayHasKey('attributes', $attributes);
                $this->assertEquals(
                    [
                        ['name' => 'venue_type', 'value' => 'warehouse', 'is_public' => true],
                    ],
                    $attributes['attributes'],
                );
                $this->assertEquals(['id' => 1, 'account_id' => 10], $where);
                return true;
            })
            ->andReturn(1);

        $dto = new UpdateEventDTO(
            title: 'Updated Title',
            category: null,
            account_id: 10,
            id: 1,
            start_date: '2026-06-01T20:00:00',
            end_date: null,
            description: 'desc',
            attributes: $newAttributes,
            timezone: 'UTC',
            currency: 'USD',
            location: null,
            location_details: null,
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedEvent, $result);
    }

    public function testPreservesExistingAttributesWhenUpdateOmitsThem(): void
    {
        $existingAttributes = [
            ['name' => 'genre', 'value' => 'techno', 'is_public' => true],
            ['name' => 'capacity_note', 'value' => 'limited', 'is_public' => false],
        ];

        $existingEvent = (new EventDomainObject())
            ->setId(1)
            ->setAccountId(10)
            ->setTitle('Original Title')
            ->setTimezone('UTC')
            ->setCurrency('USD')
            ->setCategory('OTHER')
            ->setAttributes($existingAttributes);

        $reloadedEvent = (new EventDomainObject())
            ->setId(1)
            ->setAccountId(10)
            ->setTitle('Updated Title')
            ->setTimezone('UTC')
            ->setCurrency('USD')
            ->setAttributes($existingAttributes);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 10])
            ->twice()
            ->andReturn($existingEvent, $reloadedEvent);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes, array $where) use ($existingAttributes) {
                $this->assertArrayHasKey('attributes', $attributes);
                $this->assertEquals(
                    $existingAttributes,
                    $attributes['attributes'],
                    'Existing attributes must be preserved when the update payload omits them',
                );
                return true;
            })
            ->andReturn(1);

        $dto = new UpdateEventDTO(
            title: 'Updated Title',
            category: null,
            account_id: 10,
            id: 1,
            start_date: '2026-06-01T20:00:00',
            end_date: null,
            description: 'desc',
            attributes: null,
            timezone: 'UTC',
            currency: 'USD',
            location: null,
            location_details: null,
        );

        $result = $this->handler->handle($dto);

        $this->assertEquals($existingAttributes, $result->getAttributes());
    }
}
