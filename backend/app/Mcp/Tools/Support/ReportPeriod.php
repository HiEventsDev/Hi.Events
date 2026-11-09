<?php

namespace HiEvents\Mcp\Tools\Support;

use Carbon\CarbonImmutable;

readonly class ReportPeriod
{
    public const DEFAULT_DAYS = 30;

    public const RULES = [
        'from' => 'nullable|date_format:Y-m-d|required_with:to',
        'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
    ];

    private function __construct(
        public string $from,
        public string $to,
        private string $timezone,
    ) {}

    public static function fromInput(?string $from, ?string $to, string $timezone): self
    {
        $today = CarbonImmutable::now($timezone)->toDateString();

        return new self(
            from: $from ?? CarbonImmutable::parse($today, $timezone)->subDays(self::DEFAULT_DAYS)->toDateString(),
            to: $from === null ? $today : ($to ?? max($from, $today)),
            timezone: $timezone,
        );
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    public function utcBounds(): array
    {
        return [
            'start_date' => CarbonImmutable::parse($this->from, $this->timezone)->startOfDay()->utc()->toIso8601String(),
            'end_date' => CarbonImmutable::parse($this->to, $this->timezone)->endOfDay()->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}
