import {useMemo, useState} from "react";
import {Button, LoadingOverlay, Modal} from "@mantine/core";
import {t} from "@lingui/macro";
import {bandOf, indexLayout, seatLabel} from "../lib/layoutIndex.ts";
import {SeatMapLayout} from "../lib/types.ts";
import {useSeatStates} from "../lib/useSeatStates.ts";
import {AreaTabs} from "../AreaTabs.tsx";
import {SeatMapRenderer} from "../SeatMapRenderer";
import {SeatOccupancy} from "./occupancy.ts";
import classes from "./SeatChooser.module.scss";

interface SeatChooserProps {
    title: string;
    confirmLabel: string;
    layout: SeatMapLayout;
    occupancy: SeatOccupancy | undefined;
    selectableBands: Set<string>;
    canSelectBlocked: boolean;
    canSelectZones: boolean;
    maxSeats: number;
    initialSeatUids: string[];
    isConfirming: boolean;
    onConfirm: (seatUids: string[]) => void;
    onClose: () => void;
}

export const SeatChooser = ({
    title,
    confirmLabel,
    layout,
    occupancy,
    selectableBands,
    canSelectBlocked,
    canSelectZones,
    maxSeats,
    initialSeatUids,
    isConfirming,
    onConfirm,
    onClose,
}: SeatChooserProps) => {
    const index = useMemo(() => indexLayout(layout), [layout]);
    const [areaId, setAreaId] = useState(index.seats.get(initialSeatUids[0])?.area.id ?? layout.areas[0].id);
    const [chosen, setChosen] = useState<string[]>(initialSeatUids);
    const area = layout.areas.find(candidate => candidate.id === areaId) ?? layout.areas[0];

    const chosenSet = useMemo(() => new Set(chosen), [chosen]);
    const seatStates = useSeatStates(layout.areas, {
        selected: chosenSet,
        blocked: occupancy?.blocked,
        unavailable: occupancy?.unavailable,
        sellableBands: selectableBands,
    });

    const isSelectable = (uid: string): boolean =>
        occupancy !== undefined
        && selectableBands.has(bandOf(index, uid) ?? '')
        && !occupancy.unavailable.has(uid)
        && (canSelectBlocked || !occupancy.blocked.has(uid));

    const add = (uid: string) => setChosen(current => (maxSeats === 1 ? [uid] : [...current, uid].slice(0, maxSeats)));

    const toggleSeat = (uid: string) => {
        if (chosenSet.has(uid)) {
            setChosen(current => current.filter(candidate => candidate !== uid));
        } else if (isSelectable(uid)) {
            add(uid);
        }
    };

    const addZonePlace = (uid: string) => {
        const taken = chosen.filter(candidate => candidate === uid).length;
        if (canSelectZones && selectableBands.has(bandOf(index, uid) ?? '') && (occupancy?.zoneRemaining[uid] ?? 0) > taken) {
            add(uid);
        }
    };

    const zoneSelected = useMemo(() => chosen.reduce<Record<string, number>>((counts, uid) => (
        index.zones.has(uid) ? {...counts, [uid]: (counts[uid] ?? 0) + 1} : counts
    ), {}), [chosen, index]);

    return (
        <Modal opened onClose={onClose} title={title} size="90%">
            <div className={classes.chooser}>
                <AreaTabs areas={layout.areas} value={area.id} onChange={setAreaId}/>

                <div className={classes.map} data-testid="seat-chooser-map">
                    <SeatMapRenderer area={area} bands={index.bands} seatStates={seatStates} interactive wheelZoom="always"
                                     zoneRemaining={occupancy?.zoneRemaining} zoneSelected={zoneSelected}
                                     onSeatClick={toggleSeat} onZoneClick={addZonePlace}/>
                    <LoadingOverlay visible={occupancy === undefined}/>
                </div>

                <div className={classes.footer}>
                    <span className={classes.summary}>
                        {chosen.length === 0 ? t`Choose a seat on the map` : chosen.map(uid => seatLabel(index, uid)).join(', ')}
                    </span>
                    {chosen.length > 0 && maxSeats > 1 && (
                        <Button variant="subtle" onClick={() => setChosen([])}>{t`Clear`}</Button>
                    )}
                    <Button disabled={chosen.length === 0} loading={isConfirming} onClick={() => onConfirm(chosen)}
                            data-testid="seat-chooser-confirm-button">
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};
