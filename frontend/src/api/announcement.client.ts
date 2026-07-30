import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam} from "../types";

export type AnnouncementDisplayType = 'BANNER' | 'MODAL';
export type AnnouncementTargetType = 'ALL' | 'ACCOUNTS' | 'USERS';
export type AnnouncementStatus = 'DRAFT' | 'PUBLISHED';

export interface Announcement {
    id: IdParam;
    title: string;
    content: string;
    display_type: AnnouncementDisplayType;
    emoji: string | null;
    cta_label: string | null;
    cta_url: string | null;
}

export interface AdminAnnouncement extends Announcement {
    status: AnnouncementStatus;
    target_type: AnnouncementTargetType;
    target_account_ids: number[] | null;
    target_user_ids: number[] | null;
    target_names: Record<number, string>;
    seen_count: number;
    dismissed_count: number;
    created_at: string;
    updated_at: string;
}

export interface UpsertAnnouncementData {
    title: string;
    content: string;
    status: AnnouncementStatus;
    display_type: AnnouncementDisplayType;
    emoji?: string | null;
    target_type: AnnouncementTargetType;
    target_account_ids?: number[];
    target_user_ids?: number[];
    cta_label?: string | null;
    cta_url?: string | null;
}

export interface GetAdminAnnouncementsParams {
    page?: number;
    per_page?: number;
    search?: string;
}

export const announcementClient = {
    all: async (params: GetAdminAnnouncementsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminAnnouncement>>('admin/announcements', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
            }
        });
        return response.data;
    },

    create: async (data: UpsertAnnouncementData) => {
        const response = await api.post<GenericDataResponse<AdminAnnouncement>>('admin/announcements', data);
        return response.data;
    },

    update: async (announcementId: IdParam, data: UpsertAnnouncementData) => {
        const response = await api.put<GenericDataResponse<AdminAnnouncement>>(`admin/announcements/${announcementId}`, data);
        return response.data;
    },

    delete: async (announcementId: IdParam) => {
        const response = await api.delete(`admin/announcements/${announcementId}`);
        return response.data;
    },

    active: async () => {
        const response = await api.get<GenericDataResponse<Announcement[]>>('announcements/active');
        return response.data;
    },

    dismiss: async (announcementId: IdParam) => {
        const response = await api.post(`announcements/${announcementId}/dismiss`);
        return response.data;
    },
};
