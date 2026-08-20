<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\Repository\DTO\CheckInListProductStatDTO;
use HiEvents\Repository\DTO\CheckInListRecentCheckInDTO;
use HiEvents\Repository\DTO\CheckInListStatsDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckInListStatsDTO
 */
class CheckInListStatsPublicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_attendees' => $this->totalAttendees,
            'checked_in_attendees' => $this->checkedInAttendees,
            'per_product' => array_map(
                static fn (CheckInListProductStatDTO $stat) => [
                    'product_id' => $stat->productId,
                    'product_title' => $stat->productTitle,
                    'total_attendees' => $stat->totalAttendees,
                    'checked_in_attendees' => $stat->checkedInAttendees,
                ],
                $this->perProduct,
            ),
            'recent_check_ins' => array_map(
                static fn (CheckInListRecentCheckInDTO $checkIn) => [
                    'attendee_public_id' => $checkIn->attendeePublicId,
                    'first_name' => $checkIn->firstName,
                    'last_name' => $checkIn->lastName,
                    'product_title' => $checkIn->productTitle,
                    'checked_in_at' => $checkIn->checkedInAt,
                ],
                $this->recentCheckIns,
            ),
        ];
    }
}
