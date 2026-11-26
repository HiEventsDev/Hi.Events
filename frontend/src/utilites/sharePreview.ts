import {t} from "@lingui/macro";
import {Event, EventSettings, Organizer, OrganizerSettings} from "../types.ts";
import {eventCoverImage} from "./urlHelper.ts";
import {htmlToText} from "./helpers.ts";
import {eventMetaDescription} from "./eventMeta.ts";

export interface SharePreviewContent {
    title: string;
    description: string;
    imageUrl?: string;
    imageBackground?: string;
}

export const eventSharePreview = (event: Event, settings: EventSettings | undefined = event.settings): SharePreviewContent => {
    const cover = eventCoverImage(event);
    const pageTitle = settings?.seo_title ?? event.title;

    return {
        title: event.organizer?.name ? `${pageTitle} | ${event.organizer.name}` : pageTitle,
        description: htmlToText(eventMetaDescription(event, settings)),
        imageUrl: cover?.url,
        imageBackground: cover?.avg_colour ?? undefined,
    };
};

export const organizerSharePreview = (
    organizer: Organizer,
    settings: OrganizerSettings | undefined = organizer.settings,
): SharePreviewContent => {
    const name = organizer.name;
    const cover = organizer.images?.find(image => image.type === 'ORGANIZER_COVER')
        ?? organizer.images?.find(image => image.type === 'ORGANIZER_LOGO');

    return {
        title: settings?.seo_title || t`${name} - Events`,
        description: settings?.seo_description || t`Discover upcoming events by ${name}.`,
        imageUrl: cover?.url,
        imageBackground: cover?.avg_colour ?? undefined,
    };
};
