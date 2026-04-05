<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEventPasswordAction extends BaseAction
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $password = $request->input('password');

        if (empty($password)) {
            return $this->jsonResponse(['valid' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObject::EVENT_ID => $eventId,
        ]);

        if (!$settings || empty($settings->getEventPassword())) {
            return $this->jsonResponse(['valid' => true]);
        }

        if (hash_equals($settings->getEventPassword(), $password)) {
            return $this->jsonResponse(['valid' => true]);
        }

        return $this->jsonResponse(['valid' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
