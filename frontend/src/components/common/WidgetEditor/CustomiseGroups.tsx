import {ReactNode} from "react";
import {Accordion, Stack} from "@mantine/core";
import {t} from "@lingui/macro";
import {Event, EventOccurrence, EventType, HomepageThemeSettings} from '../../../types.ts';
import {getEventCategories} from "../../../constants/eventCategories.ts";
import {getLocaleName, SupportedLocales} from "../../../locales.ts";
import {WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";
import {WidgetColors} from "../../../utilites/widgetTheme.ts";
import {
    ButtonFields,
    EventListFields,
    OccurrenceField,
    PromoCodeFields,
    SectionFields,
    TicketFields,
} from "./ContentFields.tsx";
import {AppearancePanel} from "./AppearancePanel.tsx";
import {BehaviourPanel} from "./BehaviourPanel.tsx";
import {LineupSectionAvailability} from "./sectionAvailability.ts";
import classes from './WidgetEditor.module.scss';

interface Group {
    key: string;
    label: string;
    summary: string;
    content: ReactNode;
}

interface CustomiseGroupsProps {
    type: WidgetType;
    settings: WidgetSettings;
    update: (patch: Partial<WidgetSettings>) => void;
    isOrganizer: boolean;
    event?: Event;
    occurrences?: EventOccurrence[];
    theme?: Partial<HomepageThemeSettings> | null;
    themeColors: WidgetColors;
    homepageDesignerPath: string;
    lineupAvailability: LineupSectionAvailability;
    locked: boolean;
}

const joined = (...parts: (string | false | undefined)[]) => parts.filter(Boolean).join(' · ');

export const CustomiseGroups = ({
    type,
    settings,
    update,
    isOrganizer,
    event,
    occurrences,
    theme,
    themeColors,
    homepageDesignerPath,
    lineupAvailability,
    locked,
}: CustomiseGroupsProps) => {
    const sellsTickets = type === 'TICKETS' || type === 'BUTTON';
    const canPinDate = !isOrganizer && event?.type === EventType.RECURRING && !!occurrences?.length && !!event.timezone;
    const occurrenceField = canPinDate && <OccurrenceField settings={settings} update={update} event={event} occurrences={occurrences}/>;
    const extraSections = (settings.sections ?? ['tickets'])
            .filter(section => section === 'lineup' || section === 'schedule' ? lineupAvailability[section] !== 'hidden' : section !== 'tickets')
            .length
        + (settings.show_lineup && lineupAvailability.lineup !== 'hidden' ? 1 : 0);
    const filtered = !!settings.categories?.length || !!settings.products?.length;
    const languageSummary = settings.locale ? getLocaleName(settings.locale as SupportedLocales) : t`Visitor's language`;

    const groups: Group[] = [];

    if (type === 'BUTTON') {
        groups.push({
            key: 'button',
            label: t`Button`,
            summary: settings.button_text || t`Get tickets`,
            content: <ButtonFields settings={settings} update={update}/>,
        });
    }

    if (sellsTickets) {
        groups.push(
            {
                key: 'sections',
                label: type === 'BUTTON' ? t`Pop-up content` : t`Sections`,
                summary: extraSections ? t`Tickets and ${extraSections} more` : t`Tickets only`,
                content: (
                    <SectionFields
                        settings={settings}
                        update={update}
                        availability={lineupAvailability}
                        homepageDesignerPath={homepageDesignerPath}
                    />
                ),
            },
            {
                key: 'tickets',
                label: t`Tickets`,
                summary: joined(filtered ? t`Selected tickets` : t`All tickets`, !!settings.occurrence_id && t`One date`),
                content: (
                    <>
                        <TicketFields settings={settings} update={update} event={event}/>
                        {occurrenceField}
                    </>
                ),
            },
            {
                key: 'promo',
                label: t`Promo code`,
                summary: settings.promo_code || (settings.hide_promo_code ? t`Hidden` : t`None`),
                content: <PromoCodeFields settings={settings} update={update}/>,
            },
        );
    }

    if (!sellsTickets && canPinDate) {
        groups.push({
            key: 'date',
            label: t`Date`,
            summary: settings.occurrence_id ? t`One date` : t`All dates`,
            content: occurrenceField,
        });
    }

    if (type === 'EVENTS') {
        const category = getEventCategories().find(candidate => candidate.id === settings.event_category)?.name;
        groups.push({
            key: 'events',
            label: t`Events`,
            summary: joined(category ?? t`All events`, settings.open_in === 'page' ? t`Opens event page` : t`Shows tickets`),
            content: <EventListFields settings={settings} update={update}/>,
        });
    }

    groups.push(
        {
            key: 'appearance',
            label: t`Appearance`,
            summary: settings.theme === 'event'
                ? (isOrganizer ? t`Matches your page` : t`Matches event page`)
                : t`Custom colours`,
            content: (
                <AppearancePanel
                    type={type}
                    settings={settings}
                    update={update}
                    isOrganizer={isOrganizer}
                    theme={theme}
                    themeColors={themeColors}
                    homepageDesignerPath={homepageDesignerPath}
                />
            ),
        },
        {
            key: 'behaviour',
            label: type === 'LINEUP' || type === 'SCHEDULE' ? t`Language & sizing` : t`Checkout & language`,
            summary: joined(
                sellsTickets && (settings.checkout === 'new-tab' ? t`New tab` : t`Pop-up`),
                languageSummary,
                settings.autoresize === false && t`Fixed height`,
            ),
            content: <BehaviourPanel type={type} settings={settings} update={update}/>,
        },
    );

    return (
        <Accordion multiple chevronPosition="right" classNames={{
            root: classes.groups,
            item: classes.group,
            control: classes.groupControl,
            label: classes.groupLabel,
            chevron: classes.groupChevron,
            content: classes.groupContent,
        }}>
            {groups.map(group => (
                <Accordion.Item key={group.key} value={group.key}>
                    <Accordion.Control data-testid={`widget-group-${group.key}`}>
                        <span className={classes.groupTitle}>{group.label}</span>
                        <span className={classes.groupSummary}>{group.summary}</span>
                    </Accordion.Control>
                    <Accordion.Panel>
                        <fieldset disabled={locked} className={classes.groupFieldset}>
                            <Stack gap="md" className={classes.fieldStack}>
                                {group.content}
                            </Stack>
                        </fieldset>
                    </Accordion.Panel>
                </Accordion.Item>
            ))}
        </Accordion>
    );
};
