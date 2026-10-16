import {useParams} from "react-router";
import {WidgetEditor} from "../../common/WidgetEditor";
import {LoadingMask} from "../../common/LoadingMask";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {useGetAllEventOccurrences} from "../../../queries/useGetAllEventOccurrences.ts";
import {EventType} from "../../../types.ts";

export const Widget = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const settingsQuery = useGetEventSettings(eventId);
    const occurrencesQuery = useGetAllEventOccurrences(eventId, eventQuery.data?.type === EventType.RECURRING);

    if (!eventQuery.data || !settingsQuery.data) {
        return <LoadingMask/>;
    }

    return (
        <WidgetEditor
            target={{eventId: String(eventId)}}
            event={eventQuery.data}
            theme={settingsQuery.data.homepage_theme_settings}
            homepageSections={settingsQuery.data.homepage_sections}
            occurrences={occurrencesQuery.data}
            homepageDesignerPath={`/manage/event/${eventId}/homepage-designer`}
            lineupPath={`/manage/event/${eventId}/lineup`}
        />
    );
};

export default Widget;
