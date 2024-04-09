import { Header } from "./Header";
import "./styles.scss";
import { EventInformation } from "./EventInformation";
import classes from "./EventHomepage.module.scss";
import { t } from "@lingui/macro";
import { SelectTickets } from "../../routes/ticket-widget/SelectTickets";
import "../../../styles/widget/default.scss";
import React, { Fragment } from "react";
import { EventDocumentHead } from "../../common/EventDocumentHead";
import { eventCoverImageUrl } from "../../../utilites/urlHelper.ts";
import { Event } from "../../../types.ts";
import { HomepageInfoMessage } from "../../common/HomepageInfoMessage/index.tsx";

interface EventHomepageProps {
  colors?: {
    background?: string;
    primary?: string;
    primaryText?: string;
    secondary?: string;
    secondaryText?: string;
  };
  continueButtonText?: string;
  event?: Event;
  promoCodeValid?: boolean;
  promoCode?: string;
}

const EventHomepage = ({ colors, continueButtonText, ...loaderData }: EventHomepageProps) => {

  const { event, promoCodeValid, promoCode } = loaderData;

  const styleOverrides = {
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
    return <HomepageInfoMessage message={t`This event is not available.`} />;
  }

  const coverImage = eventCoverImageUrl(event);


  return (
    <Fragment key={`${event.id}`}>
      {event && <EventDocumentHead event={event} />}
      {coverImage && (
        <div
          className={classes.background}
          style={{ backgroundImage: `url(${coverImage})` }}
        />
      )}
      <div
        id={"event-homepage"}
        style={styleOverrides}
        className={classes.styleContainer}
      >
        <div className={classes.container}>
          <Header event={event} />
          <div className={classes.innerContainer}>
            <div className={classes.eventInfo}>
              <EventInformation event={event} />
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
    </Fragment>
  );
};

export default EventHomepage;
