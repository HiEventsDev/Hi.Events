<?php

namespace HiEvents\Http\Actions\Organizers\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Organizer\DTO\SendOrganizerContactMessageDTO;
use HiEvents\Services\Application\Handlers\Organizer\SendOrganizerContactMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SendOrganizerContactMessagePublicAction extends BaseAction
{
    public function __construct(
        private readonly SendOrganizerContactMessageHandler $handler,
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $this->handler->handle(SendOrganizerContactMessageDTO::from([
            'organizer_id' => $organizerId,
            'account_id' => $this->isUserAuthenticated() ? $this->getAuthenticatedAccountId() : null,
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
        ]));

        return $this->jsonResponse([
            'message' => __('Message sent successfully'),
        ]);
    }
}
