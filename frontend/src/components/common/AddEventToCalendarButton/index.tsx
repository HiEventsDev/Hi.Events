import {ActionIcon, Tooltip} from '@mantine/core';
import {IconCalendarPlus} from '@tabler/icons-react';
import {t} from "@lingui/macro";
import {Event} from "../../../types.ts";
import {CalendarOptionsPopover} from "../CalendarOptionsPopover";

interface AddToCalendarProps {
    event: Event;
}

export const AddToEventCalendarButton = ({event}: AddToCalendarProps) => {
    return (
        <CalendarOptionsPopover event={event}>
            <Tooltip label={t`Add to Calendar`}>
                <ActionIcon variant="subtle">
                    <IconCalendarPlus size={20}/>
                </ActionIcon>
            </Tooltip>
        </CalendarOptionsPopover>
    );
};
