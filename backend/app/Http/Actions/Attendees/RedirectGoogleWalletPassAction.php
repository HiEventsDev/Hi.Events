<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\Exceptions\WalletPassNotAvailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Attendee\GetTicketWalletPassDataHandler;
use HiEvents\Services\Domain\Wallet\GoogleWalletPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RedirectGoogleWalletPassAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketWalletPassDataHandler $handler,
        private readonly GoogleWalletPassService $walletPassService,
    ) {}

    public function __invoke(int $eventId, string $attendeeShortId): RedirectResponse|JsonResponse
    {
        try {
            $url = $this->walletPassService->createSaveUrl($this->handler->handle($eventId, $attendeeShortId));
        } catch (WalletPassNotAvailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        return redirect()->away($url);
    }
}
