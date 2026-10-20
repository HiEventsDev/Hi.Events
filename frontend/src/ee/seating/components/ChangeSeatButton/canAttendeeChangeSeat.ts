import {Attendee, EventOccurrenceStatus, Order} from "../../../../types.ts";

export const canAttendeeChangeSeat = (
    attendee: Attendee,
    orderStatus: Order['status'],
    selfServiceEnabled: boolean,
): boolean => selfServiceEnabled
    && orderStatus === 'COMPLETED'
    && !!attendee.seat_uid
    && attendee.status === 'ACTIVE'
    && !attendee.is_checked_in
    && !!attendee.event_occurrence?.is_future
    && attendee.event_occurrence.status !== EventOccurrenceStatus.CANCELLED;
