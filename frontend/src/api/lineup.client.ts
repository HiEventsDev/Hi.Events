import {api} from "./client";
import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam, Image} from "../types";

export type ProfileLinkType =
    'SPOTIFY'
    | 'APPLE_MUSIC'
    | 'SOUNDCLOUD'
    | 'BANDCAMP'
    | 'YOUTUBE'
    | 'INSTAGRAM'
    | 'TIKTOK'
    | 'X'
    | 'FACEBOOK'
    | 'LINKEDIN'
    | 'GITHUB'
    | 'WEBSITE';

export type LineupBillingTier = 'HEADLINER' | 'FEATURED' | 'STANDARD';

export interface ProfileLink {
    type: ProfileLinkType;
    url: string;
}

export interface Profile {
    id: number;
    organizer_id?: number;
    name: string;
    tagline: string | null;
    bio: string | null;
    links: ProfileLink[];
    images: Image[];
    appearances?: ProfileAppearance[];
}

export interface ProfileAppearance {
    event_id: number;
    event_title: string | null;
    event_status: 'DRAFT' | 'LIVE' | 'ARCHIVED' | 'PENDING_MANUAL_REVIEW' | null;
    billing_tier: LineupBillingTier;
    role_label: string | null;
    is_hidden: boolean;
}

export interface LineupEntry {
    id: number;
    profile_id?: number;
    billing_tier: LineupBillingTier;
    role_label: string | null;
    is_hidden?: boolean;
    sort_order?: number;
    schedule_days?: string[];
    profile: Profile;
}

interface LineupVenue {
    name: string | null;
    structured_address: Record<string, string | null> | null;
}

export interface EventSpace {
    id: number;
    name: string;
    event_location_id?: number | null;
    venue: LineupVenue | null;
}

export interface ScheduleItem {
    id: number;
    title: string | null;
    description: string | null;
    starts_at: string;
    ends_at: string;
    event_space_id: number | null;
    lineup_entry_ids: number[];
}

export interface EventLineup {
    entries: LineupEntry[];
    spaces: EventSpace[];
    schedule_items: ScheduleItem[];
    venues?: { id: number; location: LineupVenue }[];
}

export interface UpsertProfileRequest {
    name: string;
    tagline: string | null;
    bio: string | null;
    links: string[];
}

export interface UpsertLineupEntryRequest {
    profile_id?: number;
    billing_tier: LineupBillingTier;
    role_label: string | null;
    is_hidden: boolean;
}

export interface UpsertEventSpaceRequest {
    name: string;
    event_location_id: number | null;
}

export interface UpsertScheduleItemRequest {
    title: string | null;
    description: string | null;
    starts_at: string;
    ends_at: string;
    event_space_id: number | null;
    lineup_entry_ids: number[];
}

export interface ImportLineupRequest {
    people: {
        name: string;
        tagline: string | null;
        bio: string | null;
        links: string[];
        billing_tier: LineupBillingTier | null;
        role_label: string | null;
        is_hidden: boolean | null;
    }[];
    slots: {
        starts_at: string;
        ends_at: string;
        stage: string | null;
        title: string | null;
        description: string | null;
        people: string[];
    }[];
}

export interface ImportLineupResult {
    people_created: number;
    people_updated: number;
    people_added: number;
    stages_created: number;
    slots_created: number;
    slots_skipped: number;
}

export const lineupClient = {
    getProfiles: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<Profile[]>>(`organizers/${organizerId}/profiles`);
        return response.data;
    },
    createProfile: async (organizerId: IdParam, profile: UpsertProfileRequest) => {
        const response = await api.post<GenericDataResponse<Profile>>(`organizers/${organizerId}/profiles`, profile);
        return response.data;
    },
    updateProfile: async (organizerId: IdParam, profileId: IdParam, profile: UpsertProfileRequest) => {
        const response = await api.put<GenericDataResponse<Profile>>(`organizers/${organizerId}/profiles/${profileId}`, profile);
        return response.data;
    },
    deleteProfile: async (organizerId: IdParam, profileId: IdParam) => {
        await api.delete(`organizers/${organizerId}/profiles/${profileId}`);
    },
    sortProfileImages: async (organizerId: IdParam, profileId: IdParam, sortedIds: IdParam[]) => {
        await api.post(`organizers/${organizerId}/profiles/${profileId}/images/sort`, {sorted_ids: sortedIds});
    },
    getLineup: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventLineup>>(`events/${eventId}/lineup`);
        return response.data;
    },
    exportRunSheet: async (eventId: IdParam): Promise<Blob> => {
        const response = await api.get(`events/${eventId}/lineup/export`, {responseType: 'blob'});
        return response.data;
    },
    importLineup: async (eventId: IdParam, payload: ImportLineupRequest) => {
        const response = await api.post<ImportLineupResult>(`events/${eventId}/lineup/import`, payload);
        return response.data;
    },
    createEntry: async (eventId: IdParam, entry: UpsertLineupEntryRequest) => {
        const response = await api.post<GenericDataResponse<LineupEntry>>(`events/${eventId}/lineup/entries`, entry);
        return response.data;
    },
    updateEntry: async (eventId: IdParam, entryId: IdParam, entry: UpsertLineupEntryRequest) => {
        const response = await api.put<GenericDataResponse<LineupEntry>>(`events/${eventId}/lineup/entries/${entryId}`, entry);
        return response.data;
    },
    deleteEntry: async (eventId: IdParam, entryId: IdParam) => {
        await api.delete(`events/${eventId}/lineup/entries/${entryId}`);
    },
    sortEntries: async (eventId: IdParam, sortedIds: number[]) => {
        await api.post(`events/${eventId}/lineup/entries/sort`, {sorted_ids: sortedIds});
    },
    createSpace: async (eventId: IdParam, space: UpsertEventSpaceRequest) => {
        const response = await api.post<GenericDataResponse<EventSpace>>(`events/${eventId}/lineup/spaces`, space);
        return response.data;
    },
    updateSpace: async (eventId: IdParam, spaceId: IdParam, space: UpsertEventSpaceRequest) => {
        const response = await api.put<GenericDataResponse<EventSpace>>(`events/${eventId}/lineup/spaces/${spaceId}`, space);
        return response.data;
    },
    deleteSpace: async (eventId: IdParam, spaceId: IdParam) => {
        await api.delete(`events/${eventId}/lineup/spaces/${spaceId}`);
    },
    createScheduleItem: async (eventId: IdParam, item: UpsertScheduleItemRequest) => {
        const response = await api.post<GenericDataResponse<ScheduleItem>>(`events/${eventId}/lineup/schedule-items`, item);
        return response.data;
    },
    updateScheduleItem: async (eventId: IdParam, itemId: IdParam, item: UpsertScheduleItemRequest) => {
        const response = await api.put<GenericDataResponse<ScheduleItem>>(`events/${eventId}/lineup/schedule-items/${itemId}`, item);
        return response.data;
    },
    deleteScheduleItem: async (eventId: IdParam, itemId: IdParam) => {
        await api.delete(`events/${eventId}/lineup/schedule-items/${itemId}`);
    },
};

export const lineupClientPublic = {
    getLineup: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<EventLineup>>(`events/${eventId}/lineup`);
        return response.data;
    },
};
