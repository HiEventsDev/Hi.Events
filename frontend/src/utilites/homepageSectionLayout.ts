import type {HomepageSection, HomepageSectionType} from "../types.ts";

const OPT_IN_SECTIONS: HomepageSectionType[] = ['MORE_EVENTS'];

const builtIn = (type: HomepageSectionType): HomepageSection => ({id: type.toLowerCase(), type, enabled: !OPT_IN_SECTIONS.includes(type)});

export const EVENT_DEFAULT_SECTIONS: HomepageSection[] = (
    ['TICKETS', 'ABOUT', 'LINEUP', 'SCHEDULE', 'LOCATION', 'ORGANIZER', 'MORE_EVENTS'] as HomepageSectionType[]
).map(builtIn);

export const ORGANIZER_DEFAULT_SECTIONS: HomepageSection[] = (
    ['ABOUT', 'CONTACT', 'EVENTS', 'PAST_EVENTS'] as HomepageSectionType[]
).map(builtIn);

export const withDefaultSections = (saved: HomepageSection[] | null | undefined, defaults: HomepageSection[]): HomepageSection[] => {
    const sections = [...(saved ?? [])];

    defaults.forEach((section, index) => {
        if (sections.some(candidate => candidate.type === section.type)) {
            return;
        }
        const previousType = defaults[index - 1]?.type;
        const position = previousType ? sections.findIndex(candidate => candidate.type === previousType) + 1 : 0;
        sections.splice(position, 0, section);
    });

    return sections;
};

type OrganizerHomepageBlock =
    | { kind: 'events', section: HomepageSection, includesPastEvents: boolean }
    | { kind: 'section', section: HomepageSection };

interface OrganizerHomepageLayout {
    heroAbout: HomepageSection | null;
    heroContact: HomepageSection | null;
    blocks: OrganizerHomepageBlock[];
}

const PROFILE_CARD_TYPES: HomepageSectionType[] = ['ABOUT', 'CONTACT'];

export const organizerHomepageLayout = (sections: HomepageSection[]): OrganizerHomepageLayout => {
    const visible = sections.filter(section => section.enabled || section.type === 'EVENTS');
    const layout: OrganizerHomepageLayout = {heroAbout: null, heroContact: null, blocks: []};

    let index = 0;
    for (; index < visible.length && PROFILE_CARD_TYPES.includes(visible[index].type); index++) {
        if (visible[index].type === 'ABOUT') {
            layout.heroAbout = visible[index];
        } else {
            layout.heroContact = visible[index];
        }
    }

    for (; index < visible.length; index++) {
        const section = visible[index];
        if (section.type !== 'EVENTS') {
            layout.blocks.push({kind: 'section', section});
            continue;
        }
        const includesPastEvents = visible[index + 1]?.type === 'PAST_EVENTS';
        layout.blocks.push({kind: 'events', section, includesPastEvents});
        if (includesPastEvents) {
            index++;
        }
    }

    return layout;
};
