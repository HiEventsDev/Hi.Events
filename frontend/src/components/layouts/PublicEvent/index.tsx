import {useLoaderData} from "react-router";
import EventHomepage from "../EventHomepage";
import {Event} from "../../../types";

export const PublicEvent = () => {
    const loaderData = useLoaderData();

    const {event, promoCodeValid, promoCode, occurrenceId} = loaderData as {
        event?: Event;
        promoCodeValid?: boolean;
        promoCode?: string;
        occurrenceId?: number | null;
    };

    return (
        <EventHomepage
            event={event}
            promoCodeValid={promoCodeValid}
            promoCode={promoCode}
            initialOccurrenceId={occurrenceId}
        />
    );
};

export default PublicEvent;
