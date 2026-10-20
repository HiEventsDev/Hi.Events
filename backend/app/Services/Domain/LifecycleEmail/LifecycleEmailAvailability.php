<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class LifecycleEmailAvailability
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) $this->config->get('app.saas_mode_enabled');
    }
}
