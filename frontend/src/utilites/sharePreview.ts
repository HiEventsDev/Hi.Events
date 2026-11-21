import {Event, Organizer} from "../types.ts";
import {eventCoverImage} from "./urlHelper.ts";
import {htmlToText} from "./helpers.ts";

export interface SharePreviewContent {
    title: string;
    description: string;
    imageUrl?: string;
    imageBackground?: string;
}

export const eventSharePreview = (event: Event): SharePreviewContent => {
    const cover = eventCoverImage(event);
    const pageTitle = event.settings?.seo_title ?? event.title;

    return {
        title: event.organizer?.name ? `${pageTitle} | ${event.organizer.name}` : pageTitle,
        description: htmlToText(event.settings?.seo_description ?? event.description_preview),
        imageUrl: cover?.url,
        imageBackground: cover?.avg_colour ?? undefined,
    };
};

export const organizerSharePreview = (organizer: Organizer): SharePreviewContent => {
    const cover = organizer.images?.find(image => image.type === 'ORGANIZER_COVER')
        ?? organizer.images?.find(image => image.type === 'ORGANIZER_LOGO');

    return {
        title: organizer.name,
        description: organizer.settings?.seo_description || `Discover upcoming events by ${organizer.name}.`,
        imageUrl: cover?.url,
        imageBackground: cover?.avg_colour ?? undefined,
    };
};
