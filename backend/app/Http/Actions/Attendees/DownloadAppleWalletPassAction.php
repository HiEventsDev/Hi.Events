<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Attendees;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Attendee\GetTicketWalletPassDataHandler;
use HiEvents\Services\Domain\Wallet\AppleWalletPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DownloadAppleWalletPassAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketWalletPassDataHandler $handler,
        private readonly AppleWalletPassService $walletPassService,
    ) {}

    #[ResponseAttribute(status: 200, description: 'Apple Wallet pass', mediaType: 'application/vnd.apple.pkpass', type: 'string', format: 'binary')]
    public function __invoke(int $eventId, string $attendeeShortId): Response|JsonResponse
    {
        try {
            $pass = $this->walletPassService->create($this->handler->handle($eventId, $attendeeShortId));
        } catch (WalletPassNotAvailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        return new Response($pass, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="ticket.pkpass"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
