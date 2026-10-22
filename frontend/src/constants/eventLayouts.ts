import {EventLayout} from "../types.ts";

export type SheetWidth = 'wide' | 'medium' | 'narrow';

export interface EventLayoutDefinition {
    value: EventLayout;
    width: SheetWidth;
    ticketRail: boolean;
    poster: boolean;
    sectionNav: boolean;
    centeredIntro: boolean;
}

export const DEFAULT_EVENT_LAYOUT: EventLayout = 'MARQUEE';

export const EVENT_LAYOUTS: EventLayoutDefinition[] = [
    {value: 'MARQUEE', width: 'wide', ticketRail: true, poster: false, sectionNav: true, centeredIntro: false},
    {value: 'FLYER', width: 'wide', ticketRail: false, poster: true, sectionNav: true, centeredIntro: false},
    {value: 'STORY', width: 'medium', ticketRail: false, poster: false, sectionNav: true, centeredIntro: false},
    {value: 'INVITE', width: 'narrow', ticketRail: false, poster: false, sectionNav: false, centeredIntro: true},
];

export const resolveEventLayout = (layout?: string | null): EventLayoutDefinition =>
    EVENT_LAYOUTS.find(definition => definition.value === layout)
    ?? EVENT_LAYOUTS.find(definition => definition.value === DEFAULT_EVENT_LAYOUT)!;
