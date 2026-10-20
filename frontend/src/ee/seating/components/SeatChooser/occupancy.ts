import {OccupiedSeat, SeatAvailability} from "../../api/seat-map.client.ts";
import {SeatMapLayout} from "../lib/types.ts";

export interface SeatOccupancy {
    unavailable: Set<string>;
    blocked: Set<string>;
    zoneRemaining: Record<string, number>;
}

export const occupancyFromOccupiedSeats = (layout: SeatMapLayout, occupied: OccupiedSeat[]): SeatOccupancy => {
    const zoneRemaining: Record<string, number> = {};
    layout.areas.forEach(area => area.elements.forEach(element => {
        if (element.type === 'zone') {
            zoneRemaining[element.id] = Math.max(0, element.capacity - occupied.filter(seat => seat.seat_uid === element.id).length);
        }
    }));

    const seats = occupied.filter(seat => !seat.is_zone);
    return {
        unavailable: new Set(seats.filter(seat => seat.status !== 'BLOCKED').map(seat => seat.seat_uid)),
        blocked: new Set(seats.filter(seat => seat.status === 'BLOCKED').map(seat => seat.seat_uid)),
        zoneRemaining,
    };
};

export const occupancyFromAvailability = (availability: SeatAvailability): SeatOccupancy => ({
    unavailable: new Set(availability.unavailable_seat_uids),
    blocked: new Set(),
    zoneRemaining: availability.zone_remaining,
});

export const occupancyExcludingSeats = (occupancy: SeatOccupancy, seatUids: string[]): SeatOccupancy => ({
    unavailable: new Set([...occupancy.unavailable, ...seatUids.filter(uid => !(uid in occupancy.zoneRemaining))]),
    blocked: occupancy.blocked,
    zoneRemaining: Object.fromEntries(Object.entries(occupancy.zoneRemaining).map(([zoneUid, remaining]) => [
        zoneUid,
        Math.max(0, remaining - seatUids.filter(uid => uid === zoneUid).length),
    ])),
});
