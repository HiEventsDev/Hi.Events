import {TablerIcon} from "@tabler/icons-react";
import {Organizer} from "../types.ts";
import {socialMediaConfig} from "../constants/socialMediaConfig";

export interface OrganizerSocialLink {
    platform: string;
    url: string;
    label: string;
    Icon: TablerIcon;
}

export const organizerSocialLinks = (organizer: Organizer): OrganizerSocialLink[] =>
    Object.entries(organizer.settings?.social_media_handles ?? {}).flatMap(([platform, handle]) => {
        const config = socialMediaConfig[platform as keyof typeof socialMediaConfig];
        if (!handle || !config) {
            return [];
        }
        return [{platform, url: config.baseUrl + handle, label: config.name(), Icon: config.icon}];
    });

export const websiteHostname = (url: string): string | null => {
    try {
        return new URL(url).hostname;
    } catch {
        return null;
    }
};
