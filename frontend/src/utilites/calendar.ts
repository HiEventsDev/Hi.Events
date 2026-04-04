import {Event} from "../types.ts";
import {formatAddress} from "./addressUtilities.ts";

export interface OccurrenceDateOverride {
    start_date: string;
    end_date?: string;
    label?: string;
}

const getEventLocation = (event: Event): string => {
    if (event.settings?.location_details) {
        const details = event.settings.location_details;
        const address = formatAddress(details);

        if (details.venue_name && address) {
            return `${details.venue_name}, ${address}`;
        }

        return details.venue_name || address;
    }

    return '';
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

export const createICSContent = (event: Event, occurrence?: OccurrenceDateOverride): string => {
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
        `SUMMARY:${title.replace(/\n/g, '\\n')}`,
        `DESCRIPTION:${stripHtml(event.description_preview || '').replace(/\n/g, '\\n')}`,
        `LOCATION:${getEventLocation(event)}`,
        `DTSTAMP:${formatICSDate(new Date().toISOString())}`,
        `UID:${crypto.randomUUID()}@hi.events`,
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
};

export const downloadICSFile = (event: Event, occurrence?: OccurrenceDateOverride): void => {
    const content = createICSContent(event, occurrence);
    const blob = new Blob([content], {type: 'text/calendar;charset=utf-8'});
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.setAttribute('download', `${event.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

export const createGoogleCalendarUrl = (event: Event, occurrence?: OccurrenceDateOverride): string => {
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
        location: getEventLocation(event),
        dates: `${formatGoogleDate(startDate)}/${formatGoogleDate(endDate)}`
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
};
