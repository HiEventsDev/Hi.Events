import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconCalendar} from "@tabler/icons-react";
import {Event} from "../../../types.ts";
import {CalendarOptionsPopover} from "../CalendarOptionsPopover";
import classes from './AddToCalendarCTA.module.scss';

interface AddToCalendarCTAProps {
    event: Event;
}

export const AddToCalendarCTA = ({event}: AddToCalendarCTAProps) => {
    return (
        <div className={classes.container}>
            <div className={classes.iconContainer}>
                <IconCalendar size={24}/>
            </div>
            <div className={classes.content}>
                <span className={classes.title}>{t`Don't forget!`}</span>
                <span className={classes.subtitle}>{t`Add this event to your calendar`}</span>
            </div>
            <CalendarOptionsPopover event={event}>
                <Button variant="filled" size="sm">
                    {t`Add to Calendar`}
                </Button>
            </CalendarOptionsPopover>
        </div>
    );
};
