<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\EventOccurrence;

use HiEvents\Http\Request\EventOccurrence\GenerateOccurrencesRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GenerateOccurrencesRequestTest extends TestCase
{
    public static function weekPositions(): array
    {
        return [
            'last' => [-1, true],
            'first' => [1, true],
            'fourth' => [4, true],
            'numeric string' => ['2', true],
            'null' => [null, true],
            'zero' => [0, false],
            'fifth' => [5, false],
            'second last' => [-2, false],
            'word' => ['first', false],
        ];
    }

    #[DataProvider('weekPositions')]
    public function test_week_position_accepts_only_the_documented_positions(mixed $weekPosition, bool $valid): void
    {
        $validator = Validator::make([
            'recurrence_rule' => [
                'frequency' => 'monthly',
                'range' => ['type' => 'count', 'count' => 3],
                'monthly_pattern' => 'by_day_of_week',
                'day_of_week' => 'friday',
                'week_position' => $weekPosition,
            ],
        ], (new GenerateOccurrencesRequest)->rules());

        $this->assertSame(! $valid, $validator->errors()->has('recurrence_rule.week_position'));
    }
}
