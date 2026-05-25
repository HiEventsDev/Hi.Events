import {ActionIcon, Tooltip} from '@mantine/core';
import {IconCalendarPlus} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {Event, EventOccurrence} from "../../../types.ts";
import {CalendarOptionsPopover} from "../CalendarOptionsPopover";

interface AddToCalendarProps {
    event: Event;
    occurrence?: EventOccurrence;
}

export const AddToEventCalendarButton = ({event, occurrence}: AddToCalendarProps) => {
    return (
        <CalendarOptionsPopover event={event} occurrence={occurrence}>
            <Tooltip label={t`Add to Calendar`}>
                <ActionIcon variant="subtle">
                    <IconCalendarPlus size={20}/>
                </ActionIcon>
            </Tooltip>
        </CalendarOptionsPopover>
    );
};
