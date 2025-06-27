import {IconCalendar, IconExternalLink, IconMapPin, IconWorld} from "@tabler/icons-react";
import classes from "./EventInformation.module.scss";
import {formatAddress, isAddressSet} from "../../../../utilites/addressUtilities.ts";
import {t} from "@lingui/macro";
import {Anchor, Button} from "@mantine/core";
import {LoadingMask} from "../../../common/LoadingMask";
import {ShareComponent} from "../../../common/ShareIcon";
import {eventCoverImageUrl, eventHomepageUrl, imageUrl, organizerHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {FC} from "react";
import {Event, Organizer} from "../../../../types.ts";
import {EventDateRange} from "../../../common/EventDateRange";

export const EventInformation: FC<{
    event: Event,
    organizer: Organizer,
}> = ({event, organizer}) => {

    if (!event || !organizer) {
        return <LoadingMask/>;
    }

    const organizerLogo = imageUrl('ORGANIZER_LOGO', organizer?.images);

    return (
        <>
            <div className={classes.preHeading}>
                <div className={classes.organizer}>
                    {organizerLogo && (
                        <div className={classes.organizerLogo}>
                            <img
                                src={organizerLogo}
                                alt={organizer.name}
                            />
                        </div>
                    )}
                    <h2 className={classes.organizerName}>
                        <Anchor
                            href={organizer?.status === 'LIVE' ? organizerHomepageUrl(organizer) : '#'}
                        >
                            {organizer.name}
                        </Anchor>
                    </h2>

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
                    <div className={classes.details}>
                        <IconCalendar size={20}/>
                        <div>
                            <EventDateRange event={event}/>
                        </div>
                    </div>
                </div>


                {event.settings?.is_online_event && (
                    <div className={classes.eventDetail}>
                        <div className={classes.details}>
                            <IconWorld size={20}/>
                            <div className={classes.detail}>
                                <b>{t`Online Event`}</b>
                            </div>
                        </div>
                    </div>
                )}

                {isAddressSet(event.settings?.location_details) && !event.settings?.is_online_event && (
                    <div className={classes.eventDetail}>
                        <div className={classes.details}>
                            <IconMapPin size={20}/>
                            <div className={classes.detail}>
                                <b>{event.settings?.location_details?.venue_name}</b>
                                <div>{formatAddress(event.settings.location_details)}</div>
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
        </>
    )
}
