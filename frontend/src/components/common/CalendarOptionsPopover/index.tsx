import {t} from "@lingui/macro";
import {Menu} from "@mantine/core";
import {IconBrandGoogle, IconDownload} from "@tabler/icons-react";
import {Event, EventOccurrence} from "../../../types.ts";
import {CalendarEntry, calendarEntryForEvent, createGoogleCalendarUrl, downloadICSFile} from "../../../utilites/calendar.ts";
import {ReactElement} from "react";

type CalendarOptionsPopoverProps = { children: ReactElement }
    & ({ event: Event; occurrence?: EventOccurrence } | { entry: CalendarEntry });

export const CalendarOptionsPopover = (props: CalendarOptionsPopoverProps) => {
    const entry = 'entry' in props ? props.entry : calendarEntryForEvent(props.event, props.occurrence);

    return (
        <Menu width={200} position="bottom" withArrow shadow="md" withInitialFocusPlaceholder={false}>
            <Menu.Target>
                {props.children}
            </Menu.Target>
            <Menu.Dropdown>
                <Menu.Label>{t`Choose calendar`}</Menu.Label>
                <Menu.Item
                    leftSection={<IconBrandGoogle size={16}/>}
                    onClick={() => window?.open(createGoogleCalendarUrl(entry), '_blank')}
                >
                    {t`Google Calendar`}
                </Menu.Item>
                <Menu.Item
                    leftSection={<IconDownload size={16}/>}
                    onClick={() => downloadICSFile(entry)}
                >
                    {t`Download .ics`}
                </Menu.Item>
            </Menu.Dropdown>
        </Menu>
    );
};
