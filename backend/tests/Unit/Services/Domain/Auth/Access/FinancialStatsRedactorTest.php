<?php

namespace Tests\Unit\Services\Domain\Auth\Access;

use HiEvents\Repository\DTO\Organizer\OrganizerDailyStatsResponseDTO;
use HiEvents\Repository\DTO\Organizer\OrganizerStatsResponseDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsResponseDTO;
use HiEvents\Services\Domain\Auth\Access\FinancialStatsRedactor;
use HiEvents\Services\Domain\Event\DTO\EventDailyStatsResponseDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Tests\TestCase;

class FinancialStatsRedactorTest extends TestCase
{
    private const NON_FINANCIAL_FLOATS = ['total_views'];

    public static function statsDtos(): array
    {
        return [
            [EventStatsResponseDTO::class],
            [EventDailyStatsResponseDTO::class],
            [OrganizerStatsResponseDTO::class],
            [OrganizerDailyStatsResponseDTO::class],
        ];
    }

    #[DataProvider('statsDtos')]
    public function test_every_money_field_in_the_stats_dtos_is_redacted(string $dto): void
    {
        $unredacted = collect((new ReflectionClass($dto))->getProperties(ReflectionProperty::IS_PUBLIC))
            ->filter(fn (ReflectionProperty $property) => $property->getType() instanceof ReflectionNamedType
                && $property->getType()->getName() === 'float')
            ->map(fn (ReflectionProperty $property) => $property->getName())
            ->reject(fn (string $name) => in_array($name, [...FinancialStatsRedactor::FINANCIAL_KEYS, ...self::NON_FINANCIAL_FLOATS], true))
            ->values()
            ->all();

        $this->assertSame([], $unredacted);
    }

    public function test_redacts_money_at_every_level(): void
    {
        $redacted = (new FinancialStatsRedactor)->redact([
            'total_orders' => 3,
            'total_gross_sales' => 120.5,
            'daily_stats' => [['date' => '2026-10-14', 'total_sales_gross' => 10.0, 'orders_created' => 1]],
        ]);

        $this->assertSame([
            'total_orders' => 3,
            'daily_stats' => [['date' => '2026-10-14', 'orders_created' => 1]],
        ], $redacted);
    }
}
