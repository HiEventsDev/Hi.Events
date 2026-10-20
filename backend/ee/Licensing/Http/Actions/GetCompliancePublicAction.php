<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Http\Actions;

use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;

class GetCompliancePublicAction extends BaseAction
{
    public function __construct(
        private readonly LicenceService $licenceService,
        private readonly Repository $config,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse([
            'support_email' => $this->config->get('app.platform_support_email'),
            /** @var 'ACTIVE'|'GRACE'|'LAPSED'|'NONE'|'DEV' */
            'licence_status' => $this->licenceService->status()->value,
            'white_label' => $this->licenceService->allows(LicensedFeature::WHITE_LABEL),
        ], wrapInData: true);
    }
}
