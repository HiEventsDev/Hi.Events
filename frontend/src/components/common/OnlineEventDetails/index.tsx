import {t} from "@lingui/macro";
import {Card} from "../Card";
import {EventSettings} from "../../../types.ts";

export const OnlineEventDetails = (props: { eventSettings: EventSettings }) => {
    return <>
        {(props.eventSettings.is_online_event && props.eventSettings.online_event_connection_details) && (
            <div style={{marginTop: "40px", marginBottom: "40px"}}>
                <h2>{t`Online Event Details`}</h2>
                <Card>
                    <div
                        dangerouslySetInnerHTML={{__html: props.eventSettings.online_event_connection_details as string}}/>
                </Card>
            </div>
        )}
    </>;
}
