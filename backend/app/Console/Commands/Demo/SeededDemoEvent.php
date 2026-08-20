<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Demo;

final readonly class SeededDemoEvent
{
    public function __construct(
        public int $event_id,
        public string $title,
        public string $slug,
        public int $occurrence_count,
        public array $promo_codes,
    ) {}

    public function publicPath(): string
    {
        return '/event/'.$this->event_id.'/'.$this->slug;
    }
}
