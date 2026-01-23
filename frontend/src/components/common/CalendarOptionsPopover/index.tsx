import {t} from "@lingui/macro";
import {Button, Popover, Stack, Text} from "@mantine/core";
import {IconBrandGoogle, IconDownload} from "@tabler/icons-react";
import {Event} from "../../../types.ts";
import {createGoogleCalendarUrl, downloadICSFile} from "../../../utilites/calendar.ts";
import {ReactNode} from "react";

interface CalendarOptionsPopoverProps {
    event: Event;
    children: ReactNode;
}

export const CalendarOptionsPopover = ({event, children}: CalendarOptionsPopoverProps) => {
    return (
        <Popover width={200} position="bottom" withArrow shadow="md">
            <Popover.Target>
                {children}
            </Popover.Target>
            <Popover.Dropdown>
                <Stack gap="xs">
                    <Text size="sm" fw={500}>{t`Choose calendar`}</Text>
                    <Button
                        variant="light"
                        size="xs"
                        leftSection={<IconBrandGoogle size={16}/>}
                        onClick={() => window?.open(createGoogleCalendarUrl(event), '_blank')}
                        fullWidth
                    >
                        {t`Google Calendar`}
                    </Button>
                    <Button
                        variant="light"
                        size="xs"
                        leftSection={<IconDownload size={16}/>}
                        onClick={() => downloadICSFile(event)}
                        fullWidth
                    >
                        {t`Download .ics`}
                    </Button>
                </Stack>
            </Popover.Dropdown>
        </Popover>
    );
};
