import {IconCalendar, IconCopy, IconExternalLink, IconMapPin, IconShare} from "@tabler/icons-react";
import classes from "./EventInformation.module.scss";
import {useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {prettyDate} from "../../../../utilites/dates.ts";
import {formatAddress} from "../../../../utilites/formatAddress.tsx";
import {t} from "@lingui/macro";
import {Button, UnstyledButton} from "@mantine/core";
import {LoadingMask} from "../../../common/LoadingMask";
import {ShareBar} from "../../../common/ShareBar";

export const EventInformation = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEventPublic(eventId);

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
                    <Button variant={'transparent'} leftSection={<IconShare size={20}/>}>
                        {t`Share`}
                    </Button>
                </div>
            </div>
            <h1 className={classes.eventTitle}>{event.title}</h1>
            <div className={classes.eventInfo}>
                <div className={classes.eventDetail}>
                    <h2>{t`Date & Time`}</h2>
                    <div className={classes.details}>
                        <IconCalendar size={20}/>
                        <div className={classes.detail}>
                            <div>
                                {prettyDate(event.start_date, event.timezone)}
                            </div>
                            {event?.end_date && (
                                <div>
                                    {prettyDate(event.end_date, event.timezone)}
                                </div>
                            )}
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