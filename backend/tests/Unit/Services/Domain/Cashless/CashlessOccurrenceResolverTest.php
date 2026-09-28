<?php

namespace Tests\Unit\Services\Domain\Cashless;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessOccurrenceResolver;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class CashlessOccurrenceResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_single_event_resolves_to_its_only_occurrence(): void
    {
        $resolver = $this->resolverFor([$this->occurrence(80, '+2 days', '+3 days')]);

        $this->assertSame(80, $resolver->resolveForSale(71));
    }

    public function test_the_occurrence_in_progress_wins_over_a_later_one(): void
    {
        $resolver = $this->resolverFor([
            $this->occurrence(1, '-3 days', '-2 days'),
            $this->occurrence(2, '-1 hour', '+2 hours'),
            $this->occurrence(3, '+1 day', '+2 days'),
        ]);

        $this->assertSame(2, $resolver->resolveForSale(3));
    }

    public function test_between_dates_the_next_upcoming_occurrence_is_used(): void
    {
        $resolver = $this->resolverFor([
            $this->occurrence(1, '-3 days', '-2 days'),
            $this->occurrence(2, '+1 day', '+2 days'),
            $this->occurrence(3, '+5 days', '+6 days'),
        ]);

        $this->assertSame(2, $resolver->resolveForSale(3));
    }

    public function test_once_every_date_has_passed_the_last_one_is_used(): void
    {
        $resolver = $this->resolverFor([
            $this->occurrence(1, '-5 days', '-4 days'),
            $this->occurrence(2, '-3 days', '-2 days'),
        ]);

        $this->assertSame(2, $resolver->resolveForSale(3));
    }

    public function test_an_event_without_occurrences_resolves_to_nothing(): void
    {
        $this->assertNull($this->resolverFor([])->resolveForSale(3));
    }

    private function resolverFor(array $occurrences): CashlessOccurrenceResolver
    {
        $repository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $repository->shouldReceive('findWhere')->andReturn(new Collection($occurrences));

        return new CashlessOccurrenceResolver($repository);
    }

    private function occurrence(int $id, string $start, string $end): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setStartDate(Carbon::parse($start)->toDateTimeString())
            ->setEndDate(Carbon::parse($end)->toDateTimeString());
    }
}
