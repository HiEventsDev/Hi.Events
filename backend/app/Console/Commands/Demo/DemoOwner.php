<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Demo;

final readonly class DemoOwner
{
    public function __construct(
        public int $account_id,
        public int $organizer_id,
        public int $user_id,
    ) {}
}
