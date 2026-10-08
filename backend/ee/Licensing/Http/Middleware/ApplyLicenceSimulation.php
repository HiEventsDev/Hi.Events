<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Middleware;

use Closure;
use HiEvents\Enterprise\Licensing\LicenceService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;

class ApplyLicenceSimulation
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->config->set('licence.simulation', $request->header(LicenceService::SIMULATION_HEADER));

        return $next($request);
    }
}
