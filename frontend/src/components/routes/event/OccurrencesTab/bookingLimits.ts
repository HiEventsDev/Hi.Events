import {EventOccurrence, OccurrenceTierAllocation} from "../../../../types.ts";

export const bookedLimit = (occ: EventOccurrence): number | null =>
    occ.booking_limits ? occ.booking_limits.sellable : occ.capacity ?? null;

export const cappedAllocationTotal = (allocations: OccurrenceTierAllocation[]): number | null => {
    const capped = allocations.filter((allocation) => allocation.quantity !== null);
    if (capped.length === 0) {
        return null;
    }
    return capped.reduce((sum, allocation) => sum + (allocation.quantity ?? 0), 0);
};
