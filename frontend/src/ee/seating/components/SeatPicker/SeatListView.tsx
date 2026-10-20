import {Accordion, Button, Group, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {isSeatedElement, SeatMapArea, SeatMapBand} from "../lib/types.ts";
import {SeatStates, seatStateOf} from "../lib/useSeatStates.ts";
import classes from "./SeatPicker.module.scss";

interface SeatListViewProps {
    area: SeatMapArea;
    bands: Map<string, SeatMapBand>;
    seatStates: SeatStates;
    onSeatClick: (uid: string) => void;
    onZoneClick: (uid: string) => void;
    zoneRemaining: Record<string, number>;
}

export const SeatListView = ({area, bands, seatStates, onSeatClick, onZoneClick, zoneRemaining}: SeatListViewProps) => {
    const groups = area.elements.filter(isSeatedElement).map(element => ({
        id: element.id,
        title: element.type === 'table' ? element.label : element.name,
        seats: element.seats,
    }));
    const zones = area.elements.filter(element => element.type === 'zone');

    return (
        <div className={classes.listView}>
            {zones.map(zone => zone.type === 'zone' && (
                <Group key={zone.id} justify="space-between" py="xs">
                    <Text fw={600}>{zone.label}</Text>
                    <Button size="xs" variant="light" disabled={(zoneRemaining[zone.id] ?? 0) <= 0}
                            onClick={() => onZoneClick(zone.id)}>
                        {t`Add standing tickets`}
                    </Button>
                </Group>
            ))}

            <Accordion multiple>
                {groups.map(group => {
                    const free = group.seats.filter(seat => seatStateOf(seatStates, seat.uid) !== 'unavailable').length;
                    return (
                        <Accordion.Item key={group.id} value={group.id}>
                            <Accordion.Control>
                                {group.title} <Text span c="dimmed" size="sm">· {t`${free} available`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <div className={classes.seatButtons}>
                                    {group.seats.map(seat => {
                                        const state = seatStateOf(seatStates, seat.uid);
                                        const bandName = bands.get(seat.band)?.name ?? '';
                                        return (
                                            <Button key={seat.uid} size="compact-sm" data-uid={seat.uid}
                                                    variant={state === 'selected' ? 'filled' : 'default'}
                                                    disabled={state === 'unavailable'} aria-pressed={state === 'selected'}
                                                    aria-label={[seat.label, bandName, seat.acc ? t`accessible` : '', seat.comp ? t`companion seat` : '', state === 'unavailable' ? t`unavailable` : t`available`].filter(Boolean).join(', ')}
                                                    onClick={() => onSeatClick(seat.uid)}>
                                                {seat.label}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </Accordion.Panel>
                        </Accordion.Item>
                    );
                })}
            </Accordion>
        </div>
    );
};
