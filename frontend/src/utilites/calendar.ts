import {Event, EventOccurrence, LocationType} from "../types.ts";
import {resolveEventLocation} from "./effectiveLocation.ts";
import {formatAddress} from "./addressUtilities.ts";

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

const stripHtml = (html: string): string => {
    if (typeof document === 'undefined') return html?.replace(/<[^>]*>/g, '') || '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html || '';
    return tmp.textContent || tmp.innerText || '';
};

// RFC 5545 §3.3.11 TEXT escaping. Backslash, semicolon and comma must be
// escaped; CR/LF collapse to the literal "\n" sequence. Without this, a
// crafted venue/title/description can inject extra calendar properties.
const escapeICSText = (value: string): string => {
    return (value ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\r\n|\r|\n/g, '\\n');
};

// RFC 5545 §3.1 content-line folding: lines longer than 75 octets must be
// split with CRLF + a leading whitespace continuation.
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
        // Avoid splitting in the middle of a UTF-8 sequence.
        while (end < bytes.length && (bytes[end] & 0xc0) === 0x80) {
            end--;
        }
        chunks.push(decoder.decode(bytes.subarray(cursor, end)));
        cursor = end;
        limit = 74; // continuation lines begin with a space, so 1 byte less of content
    }
    return chunks.join('\r\n ');
};

export const createICSContent = (event: Event, occurrence?: EventOccurrence): string => {
    const startDate = occurrence?.start_date || event.start_date;
    const endDate = occurrence?.end_date || event.end_date || startDate;
    const title = occurrence?.label
        ? `${event.title} - ${occurrence.label}`
        : event.title;

    return [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Hi.Events//NONSGML Event Calendar//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `DTSTART:${formatICSDate(startDate)}`,
        `DTEND:${formatICSDate(endDate)}`,
        foldICSLine(`SUMMARY:${escapeICSText(title)}`),
        foldICSLine(`DESCRIPTION:${escapeICSText(stripHtml(event.description_preview || ''))}`),
        foldICSLine(`LOCATION:${escapeICSText(getEventLocation(event, occurrence))}`),
        `DTSTAMP:${formatICSDate(new Date().toISOString())}`,
        `UID:${crypto.randomUUID()}@hi.events`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
};

export const downloadICSFile = (event: Event, occurrence?: EventOccurrence): void => {
    const content = createICSContent(event, occurrence);
    const blob = new Blob([content], {type: 'text/calendar;charset=utf-8'});
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.setAttribute('download', `${event.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

export const createGoogleCalendarUrl = (event: Event, occurrence?: EventOccurrence): string => {
    const formatGoogleDate = (date: string): string => {
        return new Date(date).toISOString().replace(/-|:|\.\d{3}/g, '');
    };

    const startDate = occurrence?.start_date || event.start_date;
    const endDate = occurrence?.end_date || event.end_date || startDate;
    const title = occurrence?.label
        ? `${event.title} - ${occurrence.label}`
        : event.title;

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: title,
        details: event.description_preview || '',
        location: getEventLocation(event, occurrence),
        dates: `${formatGoogleDate(startDate)}/${formatGoogleDate(endDate)}`
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
};
