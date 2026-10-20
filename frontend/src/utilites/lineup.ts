import {t} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {LineupBillingTier, LineupEntry, lineupClient, Profile, ScheduleItem} from "../api/lineup.client.ts";
import {Event, Image} from "../types.ts";
import {downloadBinary} from "./download.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

export const toEventLocal = (utcDate: string, tz: string) => dayjs.utc(utcDate).tz(tz);

interface LineupTerminology {
    lineup: string;
    schedule: string;
}

export const getLineupTerminology = (category?: string | null): LineupTerminology => {
    switch (category) {
        case 'MUSIC':
        case 'FESTIVAL':
        case 'NIGHTLIFE':
            return {lineup: t`Lineup`, schedule: t`Timetable`};
        case 'BUSINESS':
        case 'TECH':
        case 'EDUCATION':
            return {lineup: t`Speakers`, schedule: t`Agenda`};
        case 'WORKSHOP':
        case 'WELLNESS':
        case 'SPIRITUALITY':
        case 'SPORTS':
        case 'HOBBIES':
            return {lineup: t`Hosts`, schedule: t`Schedule`};
        default:
            return {lineup: t`Lineup`, schedule: t`Schedule`};
    }
};

export const BILLING_TIERS: LineupBillingTier[] = ['HEADLINER', 'FEATURED', 'STANDARD'];

export const billingTierLabel = (tier: LineupBillingTier): string => {
    switch (tier) {
        case 'HEADLINER':
            return t`Headliner`;
        case 'FEATURED':
            return t`Featured`;
        default:
            return t`Standard`;
    }
};

export const profilePhoto = (profile?: Profile | null): Image | undefined => profile?.images?.[0];

export const initialsFor = (name: string): string => name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(word => word[0]?.toUpperCase())
    .join('');

export const scheduleItemTitle = (item: ScheduleItem, entriesById: Map<number, LineupEntry>): string => {
    if (item.title) {
        return item.title;
    }

    return item.lineup_entry_ids
        .map(id => entriesById.get(id)?.profile.name)
        .filter(Boolean)
        .join(', ');
};

export const downloadRunSheet = async (event: Event) => downloadBinary(
    await lineupClient.exportRunSheet(event.id!),
    `${event.slug || 'event'}-run-sheet.xlsx`,
);
