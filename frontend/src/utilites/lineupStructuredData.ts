/* eslint-disable lingui/no-unlocalized-strings */
import {EventLineup, LineupEntry} from "../api/lineup.client.ts";
import {Event} from "../types.ts";
import {resolveHomepageSections} from "./homepageSections.ts";
import {profilePhoto, scheduleItemTitle, toEventLocal} from "./lineup.ts";

const MAX_SUB_EVENTS = 100;
const MUSIC_CATEGORIES = ['MUSIC', 'FESTIVAL', 'NIGHTLIFE'];

const performerFor = (entry: LineupEntry, performerType: string) => {
    const website = entry.profile.links?.find(link => link.type === 'WEBSITE')?.url;
    const sameAs = (entry.profile.links ?? []).map(link => link.url);

    return {
        "@type": performerType,
        name: entry.profile.name,
        ...(profilePhoto(entry.profile)?.url ? {image: profilePhoto(entry.profile)?.url} : {}),
        ...(website ? {url: website} : {}),
        ...(sameAs.length ? {sameAs} : {}),
    };
};

interface StructuredPlace {
    name?: string | null;
    address?: unknown;
    geo?: unknown;
}

export const lineupStructuredData = (event: Event, lineup: EventLineup | undefined, url: string | undefined, eventPlace?: StructuredPlace) => {
    if (!lineup) {
        return {};
    }

    const enabledTypes = new Set(
        resolveHomepageSections(event.settings?.homepage_sections)
            .filter(section => section.enabled)
            .map(section => section.type),
    );
    const performerType = MUSIC_CATEGORIES.includes(event.category ?? '') ? 'MusicGroup' : 'Person';
    const entriesById = new Map(lineup.entries.map(entry => [entry.id, entry]));

    const performer = enabledTypes.has('LINEUP')
        ? lineup.entries.map(entry => performerFor(entry, performerType))
        : [];

    const subEvent = enabledTypes.has('SCHEDULE')
        ? lineup.schedule_items.slice(0, MAX_SUB_EVENTS).flatMap(item => {
            const name = scheduleItemTitle(item, entriesById);
            if (!name) {
                return [];
            }
            const space = lineup.spaces.find(candidate => candidate.id === item.event_space_id);
            const placeName = [space?.name, space?.venue?.name ?? eventPlace?.name].filter(Boolean).join(', ');
            const performers = item.lineup_entry_ids
                .map(id => entriesById.get(id))
                .filter((entry): entry is LineupEntry => !!entry)
                .map(entry => performerFor(entry, performerType));

            return [{
                "@type": "Event",
                name,
                startDate: toEventLocal(item.starts_at, event.timezone).format(),
                endDate: toEventLocal(item.ends_at, event.timezone).format(),
                ...(url ? {url: `${url}#session-${item.id}`} : {}),
                ...(placeName || eventPlace?.address ? {
                    location: {
                        "@type": "Place",
                        ...(placeName ? {name: placeName} : {}),
                        ...(eventPlace?.address ? {address: eventPlace.address} : {}),
                        ...(eventPlace?.geo ? {geo: eventPlace.geo} : {}),
                    },
                } : {}),
                ...(performers.length ? {performer: performers} : {}),
            }];
        })
        : [];

    return {
        ...(performer.length ? {performer} : {}),
        ...(subEvent.length ? {subEvent} : {}),
    };
};
