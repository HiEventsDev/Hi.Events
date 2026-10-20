import {EventOccurrence, OccurrenceAllocation} from "../../../../types.ts";

export const bookedLimit = (occ: EventOccurrence): number | null =>
    occ.booking_limits ? occ.booking_limits.sellable : occ.capacity ?? null;

export const cappedAllocationTotal = (allocations: OccurrenceAllocation[]): number | null => {
    const capped = allocations.filter((allocation) => allocation.quantity !== null);
    if (capped.length === 0) {
        return null;
    }
    return capped.reduce((sum, allocation) => sum + (allocation.quantity ?? 0), 0);
};
