import {Header} from "./Header";
import './styles.scss';
import {EventInformation} from "./EventInformation";
import classes from "./EventHomepage.module.scss";
import {t} from "@lingui/macro";
import {SelectTickets} from "../../routes/ticket-widget/SelectTickets";
import '../../../styles/widget/default.scss';
import React from "react";
import {useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import {LoadingMask} from "../../common/LoadingMask";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {HomepageInfoMessage} from "../../common/HomepageInfoMessage";

interface EventHomepageProps {
    colors?: {
        background?: string;
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
    },
    continueButtonText?: string;
}

const EventHomepage = ({colors, continueButtonText}: EventHomepageProps) => {
    const {eventId} = useParams();
    const {data: event, isFetched: eventIsFetched, error} = useGetEventPublic(eventId);
    const coverImage = event?.images?.find((image) => image.type === 'EVENT_COVER');

    if (error?.response?.status === 404) {
        return <HomepageInfoMessage
            message={t`This event is not available.`}
        />
    }

    if (!eventIsFetched || !event) {
        return <LoadingMask/>;
    }

    const styleOverrides = {
        '--homepage-background-color': colors?.background || event?.settings?.homepage_background_color,
        '--homepage-primary-color': colors?.primary || event?.settings?.homepage_primary_color,
        '--homepage-primary-text-color': colors?.primaryText || event?.settings?.homepage_primary_text_color,
        '--homepage-secondary-color': colors?.secondary || event?.settings?.homepage_secondary_color,
        '--homepage-secondary-text-color': colors?.secondaryText || event?.settings?.homepage_secondary_text_color,
    } as React.CSSProperties;

    return (
        <>
            {(event && eventIsFetched) && <EventDocumentHead event={event}/>}
            {coverImage && <div className={classes.background} style={{backgroundImage: `url(${coverImage.url})`}}/>}
            <div id={'event-homepage'} style={styleOverrides} className={classes.styleContainer}>
                <div className={classes.container}>
                    <Header/>
                    <div className={classes.innerContainer}>
                        <div className={classes.eventInfo}>
                            <EventInformation/>
                        </div>

                        <div className={classes.ticketContainer}>
                            <h2>{t`Tickets`}</h2>
                            <div className={classes.ticketSelection}>
                                <SelectTickets
                                    colors={{
                                        background: 'var(--homepage-background-color)',
                                        primary: 'var(--homepage-primary-color)',
                                        primaryText: 'var(--homepage-primary-text-color)',
                                        secondary: 'var(--homepage-secondary-color)',
                                        secondaryText: 'var(--homepage-secondary-text-color)',
                                    }}
                                    continueButtonText={continueButtonText}
                                    padding={'0px'}/>
                            </div>
                        </div>
                    </div>
                </div>
                {/*<PoweredByFooter/>*/}
            </div>
        </>
    );
}

export default EventHomepage;