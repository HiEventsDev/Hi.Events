import {ActionIcon, Button, Popover, Stack, Text, Tooltip} from '@mantine/core';
import {IconBrandGoogle, IconCalendarPlus, IconDownload} from '@tabler/icons-react';
import {t} from "@lingui/macro";

interface LocationDetails {
    venue_name?: string;

    [key: string]: any;
}

interface EventSettings {
    location_details?: LocationDetails;
}

interface Event {
    title: string;
    description_preview?: string;
    description?: string;
    start_date: string;
    end_date?: string;
    settings?: EventSettings;
}

interface AddToCalendarProps {
    event: Event;
}

const eventLocation = (event: Event): string => {
    if (event.settings?.location_details) {
        const details = event.settings.location_details;
        const addressParts = [];

        if (details.street_address) addressParts.push(details.street_address);
        if (details.street_address_2) addressParts.push(details.street_address_2);
        if (details.city) addressParts.push(details.city);
        if (details.state) addressParts.push(details.state);
        if (details.postal_code) addressParts.push(details.postal_code);
        if (details.country) addressParts.push(details.country);

        const address = addressParts.join(', ');

        if (details.venue_name) {
            return `${details.venue_name}, ${address}`;
        }

        return address;
    }

    return '';
};

const createICSContent = (event: Event): string => {
    const formatDate = (date: string): string => {
        return new Date(date).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    };

    const stripHtml = (html: string): string => {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return tmp.textContent || tmp.innerText || '';
    };

    return [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Hi.Events//NONSGML Event Calendar//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `DTSTART:${formatDate(event.start_date)}`,
        `DTEND:${formatDate(event.end_date || event.start_date)}`,
        `SUMMARY:${event.title.replace(/\n/g, '\\n')}`,
        `DESCRIPTION:${stripHtml(event.description_preview || '').replace(/\n/g, '\\n')}`,
        `LOCATION:${eventLocation(event)}`,
        `DTSTAMP:${formatDate(new Date().toISOString())}`,
        `UID:${crypto.randomUUID()}@hi.events`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
};

const downloadICSFile = (event: Event): void => {
    const content = createICSContent(event);
    const blob = new Blob([content], {type: 'text/calendar;charset=utf-8'});
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.setAttribute('download', `${event.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const createGoogleCalendarUrl = (event: Event): string => {
    const formatGoogleDate = (date: string): string => {
        return new Date(date).toISOString().replace(/-|:|\.\d{3}/g, '');
    };

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: event.title,
        details: event.description_preview || '',
        location: eventLocation(event),
        dates: `${formatGoogleDate(event.start_date)}/${formatGoogleDate(event.end_date || event.start_date)}`
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
};

export const AddToEventCalendarButton = ({event}: AddToCalendarProps) => {
    return (
        <Popover width={200} position="bottom" withArrow shadow="md">
            <Popover.Target>
                <Tooltip label={t`Add to Calendar`}>
                    <ActionIcon variant="subtle">
                        <IconCalendarPlus size={20}/>
                    </ActionIcon>
                </Tooltip>
            </Popover.Target>
            <Popover.Dropdown>
                <Stack gap="xs">
                    <Text size="sm" fw={500}>{t`Add to Calendar`}</Text>
                    <Button
                        variant="light"
                        size="xs"
                        leftSection={<IconBrandGoogle size={16}/>}
                        onClick={() => window.open(createGoogleCalendarUrl(event), '_blank')}
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
