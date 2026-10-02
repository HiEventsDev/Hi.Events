import {useMemo} from "react";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {PublicEventSeatMap, seatMapClientPublic} from "../../api/seat-map.client.ts";
import {GET_SEAT_AVAILABILITY_QUERY_KEY, useGetSeatAvailability} from "../../queries/useGetSeatAvailability.ts";
import {GET_ORDER_PUBLIC_QUERY_KEY} from "../../../../queries/useGetOrderPublic.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {indexLayout, bandOf} from "../lib/layoutIndex.ts";
import {SeatChooser} from "../SeatChooser";
import {occupancyFromAvailability} from "../SeatChooser/occupancy.ts";
import type {ChangeSeatProps} from "./index.tsx";

export type ChangeSeatChooserProps = ChangeSeatProps & {seatMap: PublicEventSeatMap; onClose: () => void};

export const ChangeSeatChooser = ({eventId, orderShortId, attendee, seatMap, onClose}: ChangeSeatChooserProps) => {
    const queryClient = useQueryClient();
    const availability = useGetSeatAvailability(eventId, attendee.event_occurrence_id, true, seatMap.version).data;
    const currentBands = useMemo(() => {
        const band = bandOf(indexLayout(seatMap.layout), attendee.seat_uid as string);
        return new Set(band ? [band] : []);
    }, [seatMap, attendee.seat_uid]);
    const occupancy = useMemo(() => availability && occupancyFromAvailability(availability), [availability]);

    const changeMutation = useMutation({
        mutationFn: (seatUid: string) => seatMapClientPublic.changeAttendeeSeat(eventId, orderShortId, attendee.short_id, seatUid),
        onSuccess: () => {
            showSuccess(t`Your seat has been changed`);
            onClose();
        },
        onError: (error: any) => showError(
            error?.response?.data?.errors?.seat_uid?.[0] ?? error?.response?.data?.message ?? t`Your seat could not be changed`,
        ),
        onSettled: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_ORDER_PUBLIC_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_SEAT_AVAILABILITY_QUERY_KEY, eventId]}),
        ]),
    });

    return (
        <SeatChooser
            title={t`Choose a new seat for ${attendee.first_name}`}
            confirmLabel={t`Change seat`}
            layout={seatMap.layout}
            occupancy={occupancy}
            selectableBands={currentBands}
            canSelectBlocked={false}
            canSelectZones={false}
            maxSeats={1}
            initialSeatUids={[]}
            isConfirming={changeMutation.isPending}
            onConfirm={([seatUid]) => changeMutation.mutate(seatUid)}
            onClose={onClose}/>
    );
};
