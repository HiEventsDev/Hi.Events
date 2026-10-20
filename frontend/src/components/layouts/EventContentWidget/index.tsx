import {useParams} from "react-router";
import '../../../styles/widget/default.scss';
import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {Event, EventType} from "../../../types.ts";
import {lineupForOccurrence} from "../../../utilites/lineupSchedule.ts";
import {resolveHomepageSections} from "../../../utilites/homepageSections.ts";
import {getEventPublicQuery, WIDGET_EVENT_STALE_TIME} from "../../../queries/useGetEventPublic.ts";
import {useGetEventLineupPublic} from "../../../queries/useGetEventLineupPublic.ts";
import {useWidgetAppearance, useWidgetRuntime} from "../../widget/useWidgetRuntime.ts";
import {WidgetFrame} from "../../widget/WidgetFrame";
import {HeavySection} from "../../widget/WidgetSections/HeavySections.tsx";
import {WidgetMessage} from "../../widget/WidgetMessage";

interface EventContentWidgetProps {
    variant: 'lineup' | 'schedule';
}

const EventContentWidget = ({variant}: EventContentWidgetProps) => {
    const {eventId} = useParams();
    const {config, notFound} = useWidgetRuntime();
    const eventQuery = useQuery<Event, AxiosError>({
        ...getEventPublicQuery(eventId, null, false, config.occurrence_id ?? null),
        staleTime: WIDGET_EVENT_STALE_TIME,
        enabled: !notFound,
    });
    const lineupQuery = useGetEventLineupPublic(notFound ? undefined : eventId);
    const event = eventQuery.data;
    const appearance = useWidgetAppearance(config, event?.settings?.homepage_theme_settings);

    if (notFound || eventQuery.isError || (eventQuery.isFetched && !event)) {
        return (
            <WidgetFrame style={appearance.style} mode={appearance.mode}>
                <WidgetMessage title={t`This event is not available`}/>
            </WidgetFrame>
        );
    }

    if (!event || lineupQuery.isLoading) {
        return (
            <WidgetFrame style={appearance.style} mode={appearance.mode}>
                <div className={'hi-widget-loading'}>
                    <Loader color={appearance.colors.primaryText} size="md" type="dots"/>
                </div>
            </WidgetFrame>
        );
    }

    const occurrence = event.occurrences?.find(candidate => Number(candidate.id) === config.occurrence_id);
    const lineup = lineupQuery.data && occurrence && event.type === EventType.RECURRING
        ? lineupForOccurrence(lineupQuery.data, occurrence, event.timezone)
        : lineupQuery.data;
    const lineupEnabled = resolveHomepageSections(event.settings?.homepage_sections)
        .some(section => section.type === 'LINEUP' && section.enabled);
    const isEmpty = variant === 'lineup'
        ? !lineupEnabled || !lineup?.entries.length
        : !lineup?.schedule_items.length;

    return (
        <WidgetFrame style={appearance.style} mode={appearance.mode} testId={`${variant}-widget`}>
            {isEmpty ? (
                <WidgetMessage title={t`Coming soon`} description={t`Check back later for updates.`}/>
            ) : (
                <HeavySection section={variant} event={event} occurrence={occurrence}/>
            )}
        </WidgetFrame>
    );
};

export const LineupWidget = () => <EventContentWidget variant="lineup"/>;
export const ScheduleWidget = () => <EventContentWidget variant="schedule"/>;
