import {useLoaderData} from "react-router-dom";
import EventHomepage from "../EventHomepage";
import {Event} from "../../../types";

export const PublicEvent = () => {
    const loaderData = useLoaderData();

    const {event, promoCodeValid, promoCode} = loaderData as {
        event?: Event;
        promoCodeValid?: boolean;
        promoCode?: string;
    };

    return (
        <>
            <EventHomepage
                event={event}
                promoCodeValid={promoCodeValid}
                promoCode={promoCode}
                backgroundType={event?.settings?.homepage_background_type}
            />
        </>
    );
};

export default PublicEvent;