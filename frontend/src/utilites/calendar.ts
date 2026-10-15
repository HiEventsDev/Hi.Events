import {Event, EventOccurrence, LocationType} from "../types.ts";
import {resolveEventLocation} from "./effectiveLocation.ts";
import {formatAddress} from "./addressUtilities.ts";
import {htmlToText} from "./helpers.ts";

const getEventLocation = (event: Event, occurrence?: EventOccurrence | null): string => {
    const effective = resolveEventLocation(event, occurrence ?? null);

    if (effective?.type === LocationType.Online) {
        return effective.online_event_connection_details
            ? `Online — ${effective.online_event_connection_details.replace(/<[^>]+>/g, '').trim()}`.slice(0, 240)
            : 'Online';
    }

    if (effective?.type !== LocationType.InPerson) return '';

    const venue = effective.location?.name || effective.location?.structured_address?.venue_name || '';
    const address = effective.location?.structured_address ? formatAddress(effective.location.structured_address) : '';
    if (venue && address) return `${venue}, ${address}`;
    return venue || address;
};

const formatICSDate = (date: string): string => {
    return new Date(date).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
};

const escapeICSText = (value: string): string => {
    return (value ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\r\n|\r|\n/g, '\\n');
};

const foldICSLine = (line: string): string => {
    const encoder = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
    if (!encoder) {
        return line;
    }

    const bytes = encoder.encode(line);
    if (bytes.length <= 75) {
        return line;
    }

    const decoder = new TextDecoder();
    const chunks: string[] = [];
    let cursor = 0;
    let limit = 75;
    while (cursor < bytes.length) {
        let end = Math.min(cursor + limit, bytes.length);
        while (end < bytes.length && (bytes[end] & 0xc0) === 0x80) {
            end--;
        }
        chunks.push(decoder.decode(bytes.subarray(cursor, end)));
        cursor = end;
        limit = 74;
    }
    return chunks.join('\r\n ');
};

export interface CalendarEntry {
    title: string;
    startDate: string;
    endDate: string;
    description: string;
    location: string;
    filename: string;
}

export const calendarEntryForEvent = (event: Event, occurrence?: EventOccurrence): CalendarEntry => {
    const startDate = occurrence?.start_date || event.start_date;
    const endDate = occurrence
        ? (occurrence.end_date || occurrence.start_date)
        : (event.end_date || startDate);

    return {
        title: occurrence?.label ? `${event.title} - ${occurrence.label}` : event.title,
        startDate,
        endDate,
        description: htmlToText(event.description_preview),
        location: getEventLocation(event, occurrence),
        filename: event.title,
    };
};

export const createICSContent = (entry: CalendarEntry): string => {
    return [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Hi.Events//NONSGML Event Calendar//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `DTSTART:${formatICSDate(entry.startDate)}`,
        `DTEND:${formatICSDate(entry.endDate)}`,
        foldICSLine(`SUMMARY:${escapeICSText(entry.title)}`),
        foldICSLine(`DESCRIPTION:${escapeICSText(entry.description)}`),
        foldICSLine(`LOCATION:${escapeICSText(entry.location)}`),
        `DTSTAMP:${formatICSDate(new Date().toISOString())}`,
        `UID:${crypto.randomUUID()}@hi.events`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
};

export const downloadICSFile = (entry: CalendarEntry): void => {
    const content = createICSContent(entry);
    const blob = new Blob([content], {type: 'text/calendar;charset=utf-8'});
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.setAttribute('download', `${entry.filename.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

export const createGoogleCalendarUrl = (entry: CalendarEntry): string => {
    const formatGoogleDate = (date: string): string => {
        return new Date(date).toISOString().replace(/-|:|\.\d{3}/g, '');
    };

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: entry.title,
        details: entry.description,
        location: entry.location,
        dates: `${formatGoogleDate(entry.startDate)}/${formatGoogleDate(entry.endDate)}`
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
};
