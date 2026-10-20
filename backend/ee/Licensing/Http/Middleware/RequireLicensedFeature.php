<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Middleware;

use Closure;
use HiEvents\Enterprise\Licensing\Exceptions\FeatureNotLicensedException;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Http\Request;

class RequireLicensedFeature
{
    public function __construct(
        private readonly LicenceService $licenceService,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        if (! $this->licenceService->allows(LicensedFeature::from($feature))) {
            throw new FeatureNotLicensedException;
        }

        return $next($request);
    }
}
