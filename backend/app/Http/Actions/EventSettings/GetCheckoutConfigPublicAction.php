<?php

namespace HiEvents\Http\Actions\EventSettings;

use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use Illuminate\Http\JsonResponse;

class GetCheckoutConfigPublicAction
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $settingsRepository,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $settings = $this->settingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$settings) {
            return response()->json(['data' => ['multi_step_checkout_enabled' => false]]);
        }

        return response()->json([
            'data' => [
                'multi_step_checkout_enabled' => $settings->getMultiStepCheckoutEnabled() ?? false,
                'checkout_steps_config' => $settings->getCheckoutStepsConfig(),
            ],
        ]);
    }
}
