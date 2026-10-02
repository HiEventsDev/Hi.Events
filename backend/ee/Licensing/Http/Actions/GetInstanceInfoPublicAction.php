<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Actions;

use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicenceStatus;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetInstanceInfoPublicAction extends BaseAction
{
    public function __construct(
        private readonly LicenceService $licenceService,
        private readonly LicensedFeatureUsageService $featureUsageService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse([
            'white_label' => $this->licenceService->allows(LicensedFeature::WHITE_LABEL),
            'dev_mode' => $this->licenceService->status() === LicenceStatus::DEV
                && $this->featureUsageService->featuresInUse() !== [],
        ], wrapInData: true);
    }
}
