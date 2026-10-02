import {useMemo, useState} from "react";
import {Anchor, Group} from "@mantine/core";
import {t} from "@lingui/macro";
import {Attendee, IdParam} from "../../../../types.ts";
import {useGetEventSeatMap} from "../../queries/useGetEventSeatMap.ts";
import {useGetOccupiedSeats} from "../../queries/useGetOccupiedSeats.ts";
import {useMoveAttendeeSeat} from "../../mutations/useMoveAttendeeSeat.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {SeatChooser} from "../SeatChooser";
import {occupancyFromOccupiedSeats} from "../SeatChooser/occupancy.ts";
import {bandKeysForProduct} from "../SeatPicker/ticketOptions.ts";

const SeatMover = ({eventId, attendee, onClose}: {eventId: IdParam; attendee: Attendee; onClose: () => void}) => {
    const eventSeatMap = useGetEventSeatMap(eventId).data;
    const occupiedSeats = useGetOccupiedSeats(eventId, attendee.event_occurrence_id).data;
    const moveMutation = useMoveAttendeeSeat(eventId);
    const occupancy = useMemo(
        () => eventSeatMap && occupiedSeats ? occupancyFromOccupiedSeats(eventSeatMap.layout, occupiedSeats) : undefined,
        [eventSeatMap, occupiedSeats],
    );

    const selectableBands = useMemo(
        () => bandKeysForProduct(eventSeatMap?.band_products ?? [], Number(attendee.product_id)),
        [eventSeatMap, attendee.product_id],
    );

    if (!eventSeatMap) {
        return null;
    }

    return (
        <SeatChooser
            title={t`Move ${attendee.first_name} to another seat`}
            confirmLabel={t`Move attendee`}
            layout={eventSeatMap.layout}
            occupancy={occupancy}
            selectableBands={selectableBands}
            canSelectBlocked
            canSelectZones={false}
            maxSeats={1}
            initialSeatUids={[]}
            isConfirming={moveMutation.isPending}
            onConfirm={([seatUid]) => moveMutation.mutate({attendeeId: attendee.id as IdParam, seatUid}, {
                onSuccess: () => {
                    showSuccess(t`Attendee moved`);
                    onClose();
                },
                onError: (error: any) => showError(
                    error?.response?.data?.errors?.seat_uid?.[0] ?? error?.response?.data?.message ?? t`The attendee could not be moved`,
                ),
            })}
            onClose={onClose}/>
    );
};

export const AttendeeSeatField = ({eventId, attendee}: {eventId: IdParam; attendee: Attendee}) => {
    const [isMoving, setIsMoving] = useState(false);

    return (
        <Group gap="xs">
            <span>{attendee.seat_label}</span>
            <Anchor component="button" type="button" size="sm" onClick={() => setIsMoving(true)}
                    data-testid="attendee-change-seat-button">
                {t`Change`}
            </Anchor>
            {isMoving && <SeatMover eventId={eventId} attendee={attendee} onClose={() => setIsMoving(false)}/>}
        </Group>
    );
};
