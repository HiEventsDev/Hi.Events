import {t} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

/**
 * Shared templates for the "notify attendees" follow-up when an organizer
 * reschedules an occurrence. Two concerns this module handles carefully:
 *
 * 1. **Output format is HTML, not plain text.** The SendMessageModal uses a
 *    TipTap rich-text editor — plain-text newlines collapse into a single
 *    paragraph. Emails render the body via `{!! $message !!}` so HTML flows
 *    through end-to-end.
 *
 * 2. **Date format input differs between "old" and "new" values.** The old
 *    occurrence's start_date is a UTC string from the API; the new start/end
 *    come from the form as event-tz-local strings (YYYY-MM-DDTHH:mm, no tz
 *    marker). We format each appropriately so neither ends up double-offset.
 */

const escape = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

/**
 * Subject column is capped at 100 chars on the backend. Our template adds ~40
 * chars of fixed overhead, so clip the event title for the subject line only.
 */
const clipForSubject = (title: string, maxTitleLength = 55): string =>
    title.length <= maxTitleLength
        ? title
        : title.slice(0, maxTitleLength - 1).trimEnd() + '…';

/** Format a naive local-tz datetime string (YYYY-MM-DDTHH:mm) in the event tz. */
const formatLocal = (localString: string, tz: string): string =>
    dayjs.tz(localString, tz).format('MMM D, YYYY · h:mm A');

const formatLocalTime = (localString: string, tz: string): string =>
    dayjs.tz(localString, tz).format('h:mm A');

export const buildSingleRescheduleTemplate = (
    event: Event,
    oldOccurrence: EventOccurrence,
    newStartDate: string,
    newEndDate: string | null | undefined,
): { subject: string; message: string } => {
    const tz = event.timezone;
    const oldStart = formatDateWithLocale(oldOccurrence.start_date, 'shortDateTime', tz);
    const newStart = formatLocal(newStartDate, tz);
    const newEnd = newEndDate ? formatLocalTime(newEndDate, tz) : null;
    const newWhen = newEnd ? `${newStart} – ${newEnd}` : newStart;

    const title = escape(event.title);
    const organizer = escape(event.organizer?.name ?? '');

    const subjectTitle = clipForSubject(event.title);
    const subject = t`Update: ${subjectTitle} — session time changed`;

    // Built as HTML so TipTap renders paragraphs and the email preserves them.
    const message = [
        `<p>${t`Hi,`}</p>`,
        `<p>${t`The session for "${title}" originally scheduled for ${escape(oldStart)} has been rescheduled.`}</p>`,
        `<p><strong>${t`New time:`}</strong> ${escape(newWhen)}</p>`,
        `<p>${t`Your ticket is still valid — no action is needed unless the new time doesn't work for you. Please reply to this email if you have any questions.`}</p>`,
        `<p>${t`Thanks,`}<br>${organizer}</p>`,
    ].join('');

    return {subject, message};
};

export const buildBulkRescheduleTemplate = (
    event: Event,
    affectedCount: number,
    actionDescription: string,
): { subject: string; message: string } => {
    const title = escape(event.title);
    const organizer = escape(event.organizer?.name ?? '');
    const description = escape(actionDescription);

    const subjectTitle = clipForSubject(event.title);
    const subject = t`Update: ${subjectTitle} — schedule changes`;

    const message = [
        `<p>${t`Hi,`}</p>`,
        `<p>${t`We've made changes to the schedule for "${title}" — ${description} affecting ${affectedCount} session(s).`}</p>`,
        `<p>${t`Please check your ticket for the updated time. Your tickets are still valid — no action is needed unless the new times don't work for you. Reply to this email if you have any questions.`}</p>`,
        `<p>${t`Thanks,`}<br>${organizer}</p>`,
    ].join('');

    return {subject, message};
};
