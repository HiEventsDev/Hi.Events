<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;

class CompanionSeatRule
{
    /**
     * @param  array<int, array{accessible: bool, companion: bool}>  $seats
     */
    public function excessCompanions(array $seats): int
    {
        $wheelchairSpaces = count(array_filter($seats, static fn (array $seat) => $seat['accessible']));
        $companions = count(array_filter($seats, static fn (array $seat) => $seat['companion']));

        return max(0, $companions - $wheelchairSpaces);
    }

    /**
     * @param  string[]  $seatUids  the seats one order holds on one date
     *
     * @throws SeatSelectionInvalidException
     */
    public function assertAccompanied(SeatMapIndex $index, array $seatUids): void
    {
        $seats = array_map(static fn (string $uid) => [
            'accessible' => $index->isWheelchairSpace($uid),
            'companion' => $index->isCompanionSeat($uid),
        ], $seatUids);

        if ($this->excessCompanions($seats) > 0) {
            throw new SeatSelectionInvalidException(__('Companion seats can only be booked together with a wheelchair space'));
        }
    }
}
