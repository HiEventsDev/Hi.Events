import {t} from "@lingui/macro";
import {Event, HomepageSection, IdParam, Organizer, OrganizerStatus} from "../../../../../types.ts";
import {EventLineup} from "../../../../../api/lineup.client.ts";
import {buildEventLocationDisplay, summariseEventLocations} from "../../../../../utilites/effectiveLocation.ts";
import {videoEmbedUrl} from "../../../../layouts/EventHomepage/Sections/VideoSection.tsx";

export interface SectionEmptyState {
    reason: string;
    action?: { label: string, to: string, organizerId?: IdParam };
}

const hasVisibleText = (html?: string | null) => !!html && html.replace(/<[^>]+>/g, '').trim().length > 0;

const hasLocation = (event: Event) => {
    const summary = summariseEventLocations(event);
    if (summary.kind !== 'single') {
        return false;
    }
    const display = buildEventLocationDisplay(event, summary.eventLocation, summary.isEventDefault);
    return !!display && !display.isOnline;
};

export const sectionEmptyState = (
    section: HomepageSection,
    event: Event | undefined,
    lineup: EventLineup | undefined,
    imageIds: Set<number>,
): SectionEmptyState | null => {
    const eventId = event?.id;

    switch (section.type) {
        case 'ABOUT':
            return event && !hasVisibleText(event.description)
                ? {reason: t`Your event has no description yet.`, action: {label: t`Add a description`, to: `/manage/event/${eventId}/settings`}}
                : null;
        case 'LINEUP':
            return lineup && lineup.entries.length === 0
                ? {reason: t`Nobody is on the lineup yet, or everyone is hidden.`, action: {label: t`Add people`, to: `/manage/event/${eventId}/lineup`}}
                : null;
        case 'SCHEDULE':
            return lineup && lineup.schedule_items.length === 0
                ? {reason: t`No time slots yet.`, action: {label: t`Build the schedule`, to: `/manage/event/${eventId}/lineup?view=timetable`}}
                : null;
        case 'LOCATION':
            return event && !hasLocation(event)
                ? {reason: t`This event has no single in-person venue.`, action: {label: t`Set the location`, to: `/manage/event/${eventId}/settings`}}
                : null;
        case 'ORGANIZER':
        case 'MORE_EVENTS':
            return event?.organizer && event.organizer.status !== OrganizerStatus.LIVE
                ? {
                    reason: t`Your organizer profile isn't published.`,
                    action: {label: t`Organizer settings`, to: `/manage/organizer/${event.organizer.id}/settings`, organizerId: event.organizer.id},
                }
                : null;
        default:
            return customSectionEmptyState(section, imageIds);
    }
};

export const organizerSectionEmptyState = (
    section: HomepageSection,
    organizer: Organizer | undefined,
    imageIds: Set<number>,
): SectionEmptyState | null => {
    if (section.type === 'ABOUT') {
        return organizer && !hasVisibleText(organizer.description)
            ? {reason: t`Your organizer has no description yet.`, action: {label: t`Add a description`, to: `/manage/organizer/${organizer.id}/settings`}}
            : null;
    }

    return customSectionEmptyState(section, imageIds);
};

const customSectionEmptyState = (section: HomepageSection, imageIds: Set<number>): SectionEmptyState | null => {
    switch (section.type) {
        case 'TEXT':
            return hasVisibleText(section.body) ? null : {reason: t`Add some text below.`};
        case 'FAQ':
            return (section.faqs ?? []).some(faq => faq.question.trim()) ? null : {reason: t`Add at least one question below.`};
        case 'VIDEO':
            return videoEmbedUrl(section.video_url) ? null : {reason: t`Paste a YouTube or Vimeo link below.`};
        case 'GALLERY':
            return (section.image_ids ?? []).some(id => imageIds.has(id)) ? null : {reason: t`Upload some photos below.`};
        case 'SPONSORS':
            return (section.sponsors ?? []).some(sponsor => imageIds.has(sponsor.image_id)) ? null : {reason: t`Upload sponsor logos below.`};
        default:
            return null;
    }
};
