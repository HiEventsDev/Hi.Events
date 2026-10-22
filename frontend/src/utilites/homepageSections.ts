import {t} from "@lingui/macro";
import {HomepageSection, HomepageSectionType} from "../types.ts";
import {EVENT_DEFAULT_SECTIONS, ORGANIZER_DEFAULT_SECTIONS, withDefaultSections} from "./homepageSectionLayout.ts";

export const CUSTOM_SECTION_TYPES: HomepageSectionType[] = ['TEXT', 'GALLERY', 'FAQ', 'VIDEO', 'SPONSORS'];

export const homepageSectionHeading = (sections: HomepageSection[] | null | undefined, type: HomepageSectionType, fallback: string): string =>
    resolveHomepageSections(sections).find(section => section.type === type)?.heading || fallback;

export const resolveHomepageSections = (saved?: HomepageSection[] | null): HomepageSection[] =>
    withDefaultSections(saved, EVENT_DEFAULT_SECTIONS);

export const resolveOrganizerHomepageSections = (saved?: HomepageSection[] | null): HomepageSection[] =>
    withDefaultSections(saved, ORGANIZER_DEFAULT_SECTIONS);

export const sectionTypeLabel = (type: HomepageSectionType): string => {
    switch (type) {
        case 'TICKETS':
            return t`Tickets`;
        case 'ABOUT':
            return t`About`;
        case 'LINEUP':
            return t`Lineup`;
        case 'SCHEDULE':
            return t`Schedule`;
        case 'LOCATION':
            return t`Location`;
        case 'ORGANIZER':
            return t`Organizer`;
        case 'MORE_EVENTS':
            return t`More events`;
        case 'EVENTS':
            return t`Events`;
        case 'CONTACT':
            return t`Contact`;
        case 'PAST_EVENTS':
            return t`Past events`;
        case 'TEXT':
            return t`Text`;
        case 'GALLERY':
            return t`Photo gallery`;
        case 'FAQ':
            return t`FAQ`;
        case 'VIDEO':
            return t`Video`;
        case 'SPONSORS':
            return t`Sponsors`;
    }
};

export const sectionTypeDescription = (type: HomepageSectionType): string => {
    switch (type) {
        case 'TEXT':
            return t`A heading and formatted text. Travel info, house rules, anything.`;
        case 'GALLERY':
            return t`A grid of photos that opens full screen.`;
        case 'FAQ':
            return t`Questions and answers people can expand.`;
        case 'VIDEO':
            return t`A YouTube or Vimeo video, loaded when someone presses play.`;
        case 'SPONSORS':
            return t`Logos of your sponsors and partners, with links.`;
        default:
            return '';
    }
};

export const newSectionId = (): string => Math.random().toString(36).slice(2, 10);

export const cleanHomepageSections = (sections: HomepageSection[]): HomepageSection[] => sections.map(section => section.faqs
    ? {...section, faqs: section.faqs.filter(faq => faq.question.trim())}
    : section);
