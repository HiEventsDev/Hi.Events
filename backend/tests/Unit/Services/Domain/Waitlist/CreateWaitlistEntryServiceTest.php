<?php

namespace Tests\Unit\Services\Domain\Waitlist;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\EmailHelper;
use HiEvents\Jobs\Waitlist\SendWaitlistConfirmationEmailJob;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\CreateWaitlistEntryDTO;
use HiEvents\Services\Domain\Waitlist\CreateWaitlistEntryService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateWaitlistEntryServiceTest extends TestCase
{
    private CreateWaitlistEntryService $service;

    private MockInterface|WaitlistEntryRepositoryInterface $waitlistEntryRepository;

    private MockInterface|DatabaseManager $databaseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service = new CreateWaitlistEntryService(
            waitlistEntryRepository: $this->waitlistEntryRepository,
            databaseManager: $this->databaseManager,
        );
    }

    public function test_successfully_creates_waitlist_entry_with_correct_position(): void
    {
        Bus::fake();

        $dto = new CreateWaitlistEntryDTO(
            event_id: 1,
            product_price_id: 10,
            email: 'test@example.com',
            first_name: 'John',
            last_name: 'Doe',
            locale: 'en',
        );

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getWaitlistEnabled')->andReturn(true);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'email' => 'test@example.com',
                'event_id' => 1,
                ['status', 'in', [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFERED->name]],
                'product_price_id' => 10,
                'event_occurrence_id' => null,
            ])
            ->andReturnNull();

        $this->waitlistEntryRepository
            ->shouldReceive('lockForProductPrice')
            ->once()
            ->with(10, null);

        $this->waitlistEntryRepository
            ->shouldReceive('getMaxPosition')
            ->once()
            ->with(10, null)
            ->andReturn(3);

        $createdEntry = new WaitlistEntryDomainObject;
        $createdEntry->setId(1);
        $createdEntry->setEventId(1);
        $createdEntry->setProductPriceId(10);
        $createdEntry->setEmail('test@example.com');
        $createdEntry->setFirstName('John');
        $createdEntry->setLastName('Doe');
        $createdEntry->setStatus(WaitlistEntryStatus::WAITING->name);
        $createdEntry->setPosition(4);

        $this->waitlistEntryRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($attributes) {
                return $attributes['event_id'] === 1
                    && $attributes['product_price_id'] === 10
                    && $attributes['event_occurrence_id'] === null
                    && $attributes['email'] === 'test@example.com'
                    && $attributes['first_name'] === 'John'
                    && $attributes['last_name'] === 'Doe'
                    && $attributes['status'] === WaitlistEntryStatus::WAITING->name
                    && $attributes['position'] === 4
                    && ! empty($attributes['cancel_token'])
                    && $attributes['locale'] === 'en';
            }))
            ->andReturn($createdEntry);

        $result = $this->service->createEntry($dto, $eventSettings, $product);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
        $this->assertEquals(4, $result->getPosition());

        Bus::assertDispatched(SendWaitlistConfirmationEmailJob::class);
    }

    public function test_prevents_duplicate_entry_for_same_email_and_product(): void
    {
        $dto = new CreateWaitlistEntryDTO(
            event_id: 1,
            product_price_id: 10,
            email: 'duplicate@example.com',
            first_name: 'Jane',
            last_name: 'Doe',
        );

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getWaitlistEnabled')->andReturn(true);

        $existingEntry = Mockery::mock(WaitlistEntryDomainObject::class);

        $this->waitlistEntryRepository
            ->shouldReceive('lockForProductPrice')
            ->once()
            ->with(10, null);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'email' => 'duplicate@example.com',
                'event_id' => 1,
                ['status', 'in', [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFERED->name]],
                'product_price_id' => 10,
                'event_occurrence_id' => null,
            ])
            ->andReturn($existingEntry);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('You are already on the waitlist for this product');

        $this->service->createEntry($dto, $eventSettings, $product);
    }

    public function test_dispatches_send_waitlist_confirmation_email_job(): void
    {
        Bus::fake();

        $dto = new CreateWaitlistEntryDTO(
            event_id: 1,
            product_price_id: 10,
            email: 'confirm@example.com',
            first_name: 'Confirm',
            last_name: 'Test',
        );

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getWaitlistEnabled')->andReturn(true);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturnNull();

        $this->waitlistEntryRepository
            ->shouldReceive('lockForProductPrice')
            ->once()
            ->with(10, null);

        $this->waitlistEntryRepository
            ->shouldReceive('getMaxPosition')
            ->once()
            ->with(10, null)
            ->andReturn(0);

        $createdEntry = new WaitlistEntryDomainObject;
        $createdEntry->setId(1);

        $this->waitlistEntryRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($createdEntry);

        $this->service->createEntry($dto, $eventSettings, $product);

        Bus::assertDispatched(SendWaitlistConfirmationEmailJob::class);
    }

    public function test_prevents_duplicate_entry_with_plus_alias(): void
    {
        $dto = new CreateWaitlistEntryDTO(
            event_id: 1,
            product_price_id: 10,
            email: 'duplicate+alias@gmail.com',
            first_name: 'Jane',
            last_name: 'Doe',
        );

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getWaitlistEnabled')->andReturn(true);

        $existingEntry = Mockery::mock(WaitlistEntryDomainObject::class);

        $this->waitlistEntryRepository
            ->shouldReceive('lockForProductPrice')
            ->once()
            ->with(10, null);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'email' => 'duplicate@gmail.com',
                'event_id' => 1,
                ['status', 'in', [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFERED->name]],
                'product_price_id' => 10,
                'event_occurrence_id' => null,
            ])
            ->andReturn($existingEntry);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('You are already on the waitlist for this product');

        $this->service->createEntry($dto, $eventSettings, $product);
    }

    public function test_normalize_email_strips_plus_for_known_providers(): void
    {
        $this->assertEquals('user@gmail.com', EmailHelper::normalize('user+tag@gmail.com'));
        $this->assertEquals('user@gmail.com', EmailHelper::normalize('User+Tag@Gmail.com'));
        $this->assertEquals('user@hotmail.com', EmailHelper::normalize('user+foo@hotmail.com'));
        $this->assertEquals('user@proton.me', EmailHelper::normalize('user+bar@proton.me'));
    }

    public function test_normalize_email_preserves_plus_for_unknown_providers(): void
    {
        $this->assertEquals('user+tag@company.com', EmailHelper::normalize('user+tag@company.com'));
        $this->assertEquals('user+tag@myisp.net', EmailHelper::normalize('User+Tag@MyISP.net'));
    }

    public function test_normalize_email_trims_and_lowercases(): void
    {
        $this->assertEquals('user@example.com', EmailHelper::normalize('  User@Example.com  '));
    }

    public function test_throws_exception_when_waitlist_not_enabled_on_product(): void
    {
        $dto = new CreateWaitlistEntryDTO(
            event_id: 1,
            product_price_id: 10,
            email: 'test@example.com',
            first_name: 'Test',
            last_name: 'User',
        );

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getWaitlistEnabled')->andReturn(false);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('Waitlist is not enabled for this product');

        $this->service->createEntry($dto, $eventSettings, $product);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
