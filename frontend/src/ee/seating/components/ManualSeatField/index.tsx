import {useMemo, useState} from "react";
import {Button, Input} from "@mantine/core";
import {IconArmchair} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {IdParam} from "../../../../types.ts";
import {useGetEventSeatMap} from "../../queries/useGetEventSeatMap.ts";
import {useGetOccupiedSeats} from "../../queries/useGetOccupiedSeats.ts";
import {indexLayout, seatLabel} from "../lib/layoutIndex.ts";
import {SeatChooser} from "../SeatChooser";
import {occupancyFromOccupiedSeats} from "../SeatChooser/occupancy.ts";
import {bandKeysForProduct} from "../SeatPicker/ticketOptions.ts";

interface ManualSeatFieldProps {
    eventId: IdParam;
    hasSeatMap: boolean;
    productId: IdParam | undefined;
    occurrenceId: IdParam | undefined;
    value: string | undefined;
    error: React.ReactNode;
    onChange: (seatUid: string) => void;
}

const SeatField = ({eventId, productId, occurrenceId, value, error, onChange}: Omit<ManualSeatFieldProps, 'hasSeatMap'>) => {
    const eventSeatMap = useGetEventSeatMap(eventId).data;
    const occupiedSeats = useGetOccupiedSeats(eventId, occurrenceId).data;
    const [isChoosing, setIsChoosing] = useState(false);
    const index = useMemo(() => eventSeatMap ? indexLayout(eventSeatMap.layout) : null, [eventSeatMap]);
    const selectableBands = useMemo(
        () => bandKeysForProduct(eventSeatMap?.band_products ?? [], Number(productId)),
        [eventSeatMap, productId],
    );
    const occupancy = useMemo(
        () => eventSeatMap && occupiedSeats ? occupancyFromOccupiedSeats(eventSeatMap.layout, occupiedSeats) : undefined,
        [eventSeatMap, occupiedSeats],
    );

    if (!eventSeatMap || !index || selectableBands.size === 0) {
        return null;
    }

    return (
        <Input.Wrapper label={t`Seat`} required error={error}>
            <div>
                <Button variant="default" leftSection={<IconArmchair size={16}/>} disabled={occurrenceId === undefined}
                        onClick={() => setIsChoosing(true)} data-testid="manual-attendee-choose-seat-button">
                    {value ? seatLabel(index, value) : t`Choose a seat`}
                </Button>
            </div>
            {isChoosing && (
                <SeatChooser
                    title={t`Choose a seat`}
                    confirmLabel={t`Use this seat`}
                    layout={eventSeatMap.layout}
                    occupancy={occupancy}
                    selectableBands={selectableBands}
                    canSelectBlocked
                    canSelectZones
                    maxSeats={1}
                    initialSeatUids={value ? [value] : []}
                    isConfirming={false}
                    onConfirm={([seatUid]) => {
                        onChange(seatUid);
                        setIsChoosing(false);
                    }}
                    onClose={() => setIsChoosing(false)}/>
            )}
        </Input.Wrapper>
    );
};

export const ManualSeatField = ({hasSeatMap, ...props}: ManualSeatFieldProps) => (
    hasSeatMap && props.productId ? <SeatField {...props}/> : null
);
