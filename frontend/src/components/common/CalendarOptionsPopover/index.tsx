import {t} from "@lingui/macro";
import {Button, Popover, Stack, Text} from "@mantine/core";
import {IconBrandGoogle, IconDownload} from "@tabler/icons-react";
import {Event, EventOccurrence} from "../../../types.ts";
import {CalendarEntry, calendarEntryForEvent, createGoogleCalendarUrl, downloadICSFile} from "../../../utilites/calendar.ts";
import {cloneElement, ReactElement, useState} from "react";

type CalendarOptionsPopoverProps = { children: ReactElement<{ onClick?: () => void }> }
    & ({ event: Event; occurrence?: EventOccurrence } | { entry: CalendarEntry });

export const CalendarOptionsPopover = (props: CalendarOptionsPopoverProps) => {
    const entry = 'entry' in props ? props.entry : calendarEntryForEvent(props.event, props.occurrence);
    const [opened, setOpened] = useState(false);

    return (
        <Popover width={200} position="bottom" withArrow shadow="md" opened={opened} onChange={setOpened}>
            <Popover.Target>
                {cloneElement(props.children, {onClick: () => setOpened(current => !current)})}
            </Popover.Target>
            <Popover.Dropdown>
                <Stack gap="xs">
                    <Text size="sm" fw={500}>{t`Choose calendar`}</Text>
                    <Button
                        variant="light"
                        size="xs"
                        leftSection={<IconBrandGoogle size={16}/>}
                        onClick={() => {
                            setOpened(false);
                            window?.open(createGoogleCalendarUrl(entry), '_blank');
                        }}
                        fullWidth
                    >
                        {t`Google Calendar`}
                    </Button>
                    <Button
                        variant="light"
                        size="xs"
                        leftSection={<IconDownload size={16}/>}
                        onClick={() => {
                            setOpened(false);
                            downloadICSFile(entry);
                        }}
                        fullWidth
                    >
                        {t`Download .ics`}
                    </Button>
                </Stack>
            </Popover.Dropdown>
        </Popover>
    );
};
