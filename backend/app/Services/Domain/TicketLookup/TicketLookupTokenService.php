<?php

namespace HiEvents\Services\Domain\TicketLookup;

use Carbon\Carbon;
use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;

class TicketLookupTokenService
{
    public function __construct(
        private readonly TicketLookupTokenRepositoryInterface $ticketLookupTokenRepository,
    ) {}

    /**
     * @throws InvalidTicketLookupTokenException
     */
    public function emailFor(string $token): string
    {
        $record = $this->ticketLookupTokenRepository->findFirstWhere(['token' => $token]);

        if ($record === null) {
            throw new InvalidTicketLookupTokenException(__('Invalid or expired link. Please request a new one.'));
        }

        if ((new Carbon($record->getExpiresAt()))->isPast()) {
            throw new InvalidTicketLookupTokenException(__('This link has expired. Please request a new one.'));
        }

        return $record->getEmail();
    }
}
