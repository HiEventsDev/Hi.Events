import {useEffect, useState} from "react";
import {ActionIcon, Tooltip} from "@mantine/core";
import {IconArmchair} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Attendee, IdParam} from "../../../../types.ts";
import {useGetEventSeatMapPublic} from "../../queries/useGetEventSeatMapPublic.ts";
import {createLazyModule} from "../../../../utilites/lazyModule.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import type {ChangeSeatChooserProps} from "./ChangeSeatChooser.tsx";

export interface ChangeSeatProps {
    eventId: IdParam;
    orderShortId: string;
    attendee: Attendee;
}

const changeSeatChooserModule = createLazyModule(() => import("./ChangeSeatChooser.tsx"));

const LazyChangeSeatChooser = (props: ChangeSeatChooserProps) => {
    const module = changeSeatChooserModule.useModule();

    return module ? <module.ChangeSeatChooser {...props}/> : null;
};

export const ChangeSeatButton = (props: ChangeSeatProps) => {
    const seatMap = useGetEventSeatMapPublic(props.eventId, true).data;
    const [isChoosing, setIsChoosing] = useState(false);
    const canChange = !!seatMap?.allow_seat_change;

    useEffect(() => {
        if (canChange) {
            changeSeatChooserModule.load().catch(() => undefined);
        }
    }, [canChange]);

    if (!seatMap || !canChange) {
        return null;
    }

    return (
        <>
            <Tooltip label={t`Change seat`}>
                <ActionIcon variant="subtle" aria-label={t`Change seat`} data-testid="order-change-seat-button"
                            onClick={() => changeSeatChooserModule.load().then(
                                () => setIsChoosing(true),
                                () => showError(t`Something went wrong. Please try again.`),
                            )}>
                    <IconArmchair size={18}/>
                </ActionIcon>
            </Tooltip>
            {isChoosing && <LazyChangeSeatChooser {...props} seatMap={seatMap} onClose={() => setIsChoosing(false)}/>}
        </>
    );
};
