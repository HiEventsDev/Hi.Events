import {IconCalendar, IconExternalLink, IconMapPin} from "@tabler/icons-react";
import classes from "./EventInformation.module.scss";
import {prettyDate} from "../../../../utilites/dates.ts";
import {formatAddress} from "../../../../utilites/formatAddress.tsx";
import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {LoadingMask} from "../../../common/LoadingMask";
import {ShareComponent} from "../../../common/ShareIcon";
import {eventCoverImageUrl, eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {FC} from "react";
import {Event} from "../../../../types.ts";
import {EventDateRange} from "../../../common/EventDateRange";

export const EventInformation: FC<{
    event: Event
}> = ({event}) => {

    if (!event) {
        return <LoadingMask/>;
    }

    return (
        <>
            <div className={classes.preHeading}>
                <div className={classes.date}>
                    {prettyDate(event.start_date, event.timezone)}
                </div>
                <div className={classes.shareButtons}>
                    <ShareComponent
                        title={'Check out this event: ' + event.title}
                        text={'Check out this event: ' + event.title}
                        url={eventHomepageUrl(event)}
                        imageUrl={eventCoverImageUrl(event)}
                    />
                </div>
            </div>
            <h1 className={classes.eventTitle}>{event.title}</h1>
            <div className={classes.eventInfo}>
                <div className={classes.eventDetail}>
                    <h2>{t`Date & Time`}</h2>
                    <div className={classes.details}>
                        <IconCalendar size={20}/>
                        <div>
                            <EventDateRange event={event}/>
                        </div>
                    </div>
                </div>

                {event.settings?.location_details && (
                    <div className={classes.eventDetail}>
                        <h2>{t`Location`}</h2>
                        <div className={classes.details}>
                            <IconMapPin size={25}/>
                            <div className={classes.detail}>
                                <b>{event.settings?.location_details?.venue_name}</b>
                                <div>{formatAddress(event.settings?.location_details)}</div>
                                <div>
                                    <Button
                                        className={classes.viewOnGoogleMaps}
                                        component="a"
                                        target="_blank"
                                        href={
                                            event.settings.maps_url || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(formatAddress(event?.settings?.location_details))}`}
                                        variant="transparent"
                                        size="xs"
                                        rightSection={<IconExternalLink size={15}/>}
                                    >
                                        {event.settings.maps_url ? t`View map` : t`View on Google Maps`}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {event?.description && (
                <div className={classes.eventDescription}>
                    <h2>{t`About the event`}</h2>
                    <div dangerouslySetInnerHTML={{
                        __html: event.description || '',
                    }}/>
                </div>
            )}
        </>
    )
}