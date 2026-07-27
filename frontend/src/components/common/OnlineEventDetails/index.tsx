import {t} from "@lingui/macro";
import {Card} from "../Card";
import {Event, EventOccurrence, LocationType} from "../../../types.ts";
import {resolveEventLocation} from "../../../utilites/effectiveLocation.ts";

interface OnlineEventDetailsProps {
    event?: Event | null;
    occurrence?: EventOccurrence | null;
}

export const OnlineEventDetails = (props: OnlineEventDetailsProps) => {
    if (!props.event) return null;
    const eventLocation = resolveEventLocation(props.event, props.occurrence ?? null);

    if (eventLocation?.type !== LocationType.Online || !eventLocation.online_event_connection_details) {
        return null;
    }
    const details = eventLocation.online_event_connection_details;

    return (
        <div style={{marginTop: "40px", marginBottom: "40px"}}>
            <h2>{t`Online Event Details`}</h2>
            <Card>
                <div dangerouslySetInnerHTML={{__html: details as string}}/>
            </Card>
        </div>
    );
};
