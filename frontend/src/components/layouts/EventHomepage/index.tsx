import {Header} from "./Header";
import "./styles.scss";
import {EventInformation} from "./EventInformation";
import classes from "./EventHomepage.module.scss";
import {t} from "@lingui/macro";
import SelectTickets from "../../routes/ticket-widget/SelectTickets";
import "../../../styles/widget/default.scss";
import React from "react";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {eventCoverImageUrl} from "../../../utilites/urlHelper.ts";
import {Event} from "../../../types.ts";
import {HomepageInfoMessage} from "../../common/HomepageInfoMessage";

interface EventHomepageProps {
    colors?: {
        bodyBackground?: string;
        background?: string;
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
    };
    backgroundType?: 'COLOR' | 'MIRROR_COVER_IMAGE',
    continueButtonText?: string;
    event?: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
}

const EventHomepage = ({colors, continueButtonText, backgroundType, ...loaderData}: EventHomepageProps) => {
    const {event, promoCodeValid, promoCode} = loaderData;

    const styleOverrides = {
        "--homepage-body-background-color":
            colors?.bodyBackground || event?.settings?.homepage_body_background_color,
        "--homepage-background-color":
            colors?.background || event?.settings?.homepage_background_color,
        "--homepage-primary-color":
            colors?.primary || event?.settings?.homepage_primary_color,
        "--homepage-primary-text-color":
            colors?.primaryText || event?.settings?.homepage_primary_text_color,
        "--homepage-secondary-color":
            colors?.secondary || event?.settings?.homepage_secondary_color,
        "--homepage-secondary-text-color":
            colors?.secondaryText || event?.settings?.homepage_secondary_text_color,
    } as React.CSSProperties;

    if (!event) {
        return <HomepageInfoMessage message={t`This event is not available.`}/>;
    }

    const coverImage = eventCoverImageUrl(event);

    return (
        <div style={styleOverrides} key={`${event.id}`}>
            {event && <EventDocumentHead event={event}/>}
            {(coverImage && backgroundType === 'MIRROR_COVER_IMAGE') && (
                <div
                    className={classes.background}
                    style={{backgroundImage: `url(${coverImage})`}}
                />
            )}
            {(!coverImage || backgroundType === 'COLOR') &&
                <div className={classes.background}
                     style={{backgroundColor: 'var(--homepage-body-background-color)'}}
                />
            }
            <div
                id={"event-homepage"}
                className={classes.styleContainer}
            >
                <div className={classes.container}>
                    <Header event={event}/>
                    <div className={classes.innerContainer}>
                        <div className={classes.eventInfo}>
                            <EventInformation event={event}/>
                        </div>

                        <div className={classes.ticketContainer}>
                            <h2>{t`Tickets`}</h2>
                            <div className={classes.ticketSelection}>
                                <SelectTickets
                                    colors={{
                                        background: "var(--homepage-background-color)",
                                        primary: "var(--homepage-primary-color)",
                                        primaryText: "var(--homepage-primary-text-color)",
                                        secondary: "var(--homepage-secondary-color)",
                                        secondaryText: "var(--homepage-secondary-text-color)",
                                        bodyBackground: "var(--homepage-body-background-color)",
                                    }}
                                    continueButtonText={continueButtonText}
                                    padding={"0px"}
                                    event={event}
                                    promoCodeValid={promoCodeValid}
                                    promoCode={promoCode}
                                />
                            </div>
                        </div>
                    </div>
                </div>
                {/*<PoweredByFooter/>*/}
            </div>
        </div>
    );
};

export default EventHomepage;
