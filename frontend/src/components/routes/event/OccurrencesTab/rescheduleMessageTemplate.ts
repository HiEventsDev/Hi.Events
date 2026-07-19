import {t} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

const escape = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

const clipForSubject = (title: string, maxTitleLength = 55): string =>
    title.length <= maxTitleLength
        ? title
        : title.slice(0, maxTitleLength - 1).trimEnd() + '…';

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
    const newEnd = newEndDate
        ? (newEndDate.substring(0, 10) === newStartDate.substring(0, 10)
            ? formatLocalTime(newEndDate, tz)
            : formatLocal(newEndDate, tz))
        : null;
    const newWhen = newEnd ? `${newStart} – ${newEnd}` : newStart;

    const title = escape(event.title);
    const organizer = escape(event.organizer?.name ?? '');

    const subjectTitle = clipForSubject(event.title);
    const subject = t`Update: ${subjectTitle} — session time changed`;

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
