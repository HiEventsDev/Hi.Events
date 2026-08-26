<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OccurrenceStatusValidatorTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private OccurrenceStatusValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->validator = new OccurrenceStatusValidator($this->occurrenceRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function orderWithOccurrenceIds(array $occurrenceIds): OrderDomainObject
    {
        $items = array_map(function (?int $id) {
            $item = Mockery::mock(OrderItemDomainObject::class);
            $item->shouldReceive('getEventOccurrenceId')->andReturn($id);

            return $item;
        }, $occurrenceIds);

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getOrderItems')->andReturn(new Collection($items));

        return $order;
    }

    private function occurrence(bool $cancelled, bool $past): EventOccurrenceDomainObject|MockInterface
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('isCancelled')->andReturn($cancelled);
        $occurrence->shouldReceive('isPast')->andReturn($past);

        return $occurrence;
    }

    public function test_assert_passes_when_all_occurrences_are_active(): void
    {
        $this->occurrenceRepository->shouldReceive('findWhereIn')
            ->with('id', [1])
            ->andReturn(new Collection([$this->occurrence(cancelled: false, past: false)]));

        $this->validator->assertOrderOccurrencesArePurchasable($this->orderWithOccurrenceIds([1]));

        $this->assertTrue(true);
    }

    public function test_assert_skips_lookup_when_no_occurrence_ids(): void
    {
        $this->occurrenceRepository->shouldNotReceive('findWhereIn');

        $this->validator->assertOrderOccurrencesArePurchasable($this->orderWithOccurrenceIds([null, null]));

        $this->assertTrue(true);
    }

    public function test_assert_throws_when_occurrence_is_cancelled(): void
    {
        $this->occurrenceRepository->shouldReceive('findWhereIn')
            ->andReturn(new Collection([$this->occurrence(cancelled: true, past: false)]));

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This event date has been cancelled');

        $this->validator->assertOrderOccurrencesArePurchasable($this->orderWithOccurrenceIds([1]));
    }

    public function test_assert_throws_when_occurrence_is_past(): void
    {
        $this->occurrenceRepository->shouldReceive('findWhereIn')
            ->andReturn(new Collection([$this->occurrence(cancelled: false, past: true)]));

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This event date has already ended');

        $this->validator->assertOrderOccurrencesArePurchasable($this->orderWithOccurrenceIds([1]));
    }

    public function test_find_blocking_returns_cancelled_occurrence(): void
    {
        $blocking = $this->occurrence(cancelled: true, past: false);

        $this->occurrenceRepository->shouldReceive('findWhereIn')
            ->andReturn(new Collection([
                $this->occurrence(cancelled: false, past: false),
                $blocking,
            ]));

        $this->assertSame($blocking, $this->validator->findBlockingOccurrence($this->orderWithOccurrenceIds([1, 2])));
    }

    public function test_find_blocking_returns_null_when_all_active(): void
    {
        $this->occurrenceRepository->shouldReceive('findWhereIn')
            ->andReturn(new Collection([$this->occurrence(cancelled: false, past: false)]));

        $this->assertNull($this->validator->findBlockingOccurrence($this->orderWithOccurrenceIds([1])));
    }
}
