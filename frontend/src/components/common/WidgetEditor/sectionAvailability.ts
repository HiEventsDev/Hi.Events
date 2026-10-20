import {t} from "@lingui/macro";
import {HomepageSection, HomepageSectionType, IdParam} from "../../../types.ts";
import {resolveHomepageSections} from "../../../utilites/homepageSections.ts";
import {useGetEventLineupPublic} from "../../../queries/useGetEventLineupPublic.ts";

export type SectionAvailability = 'hidden' | 'empty' | 'available';

export interface LineupSectionAvailability {
    lineup: SectionAvailability;
    schedule: SectionAvailability;
}

export type LineupWidgetSection = keyof LineupSectionAvailability;

const isShownOnEventPage = (homepageSections: HomepageSection[] | null | undefined, type: HomepageSectionType) =>
    resolveHomepageSections(homepageSections).some(section => section.type === type && section.enabled);

export const useLineupSectionAvailability = (
    eventId: IdParam | undefined,
    homepageSections: HomepageSection[] | null | undefined,
): LineupSectionAvailability => {
    const lineupShown = isShownOnEventPage(homepageSections, 'LINEUP');
    const scheduleShown = isShownOnEventPage(homepageSections, 'SCHEDULE');
    const {data: lineup} = useGetEventLineupPublic(lineupShown || scheduleShown ? eventId : undefined);

    return {
        lineup: !lineupShown ? 'hidden' : lineup?.entries.length === 0 ? 'empty' : 'available',
        schedule: !scheduleShown ? 'hidden' : lineup?.schedule_items.length === 0 ? 'empty' : 'available',
    };
};

export const hiddenSectionsMessage = (lineupHidden: boolean, scheduleHidden: boolean): string | null => {
    if (lineupHidden && scheduleHidden) {
        return t`Lineup and Schedule are hidden on your event page, so the widget can't show them.`;
    }
    if (lineupHidden) {
        return t`Lineup is hidden on your event page, so the widget can't show it.`;
    }
    if (scheduleHidden) {
        return t`Schedule is hidden on your event page, so the widget can't show it.`;
    }
    return null;
};

export const unavailableReason = (section: LineupWidgetSection, availability: SectionAvailability): string | null => {
    if (availability === 'hidden') {
        return hiddenSectionsMessage(section === 'lineup', section === 'schedule');
    }
    if (availability === 'empty') {
        return section === 'lineup' ? t`Nobody is on the lineup yet, or everyone is hidden.` : t`No time slots yet.`;
    }
    return null;
};
