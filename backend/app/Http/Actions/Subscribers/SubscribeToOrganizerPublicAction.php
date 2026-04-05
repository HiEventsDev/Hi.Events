<?php

namespace HiEvents\Http\Actions\Subscribers;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventSubscriberRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscribeToOrganizerPublicAction extends BaseAction
{
    public function __construct(
        private readonly EventSubscriberRepositoryInterface $subscriberRepository,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'source' => 'nullable|string|in:checkout,widget,manual',
        ]);

        $email = strtolower(trim($request->input('email')));

        if ($this->subscriberRepository->subscriberExists($organizerId, $email)) {
            return $this->jsonResponse(['message' => 'Already subscribed'], 200);
        }

        $this->subscriberRepository->create([
            'organizer_id' => $organizerId,
            'event_id' => $request->input('event_id'),
            'email' => $email,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'token' => Str::random(64),
            'source' => $request->input('source', 'widget'),
            'is_confirmed' => false,
        ]);

        return $this->jsonResponse(['message' => 'Subscribed successfully'], 201);
    }
}
