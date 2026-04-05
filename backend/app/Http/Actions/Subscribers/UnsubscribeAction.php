<?php

namespace HiEvents\Http\Actions\Subscribers;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventSubscriberRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnsubscribeAction extends BaseAction
{
    public function __construct(
        private readonly EventSubscriberRepositoryInterface $subscriberRepository,
    )
    {
    }

    public function __invoke(string $token, Request $request): JsonResponse
    {
        $subscriber = $this->subscriberRepository->findByToken($token);

        if (!$subscriber) {
            return $this->jsonResponse(['message' => 'Invalid token'], 404);
        }

        $this->subscriberRepository->updateFromArray($subscriber->getId(), [
            'unsubscribed_at' => now()->toDateTimeString(),
        ]);

        return $this->jsonResponse(['message' => 'Unsubscribed successfully']);
    }
}
