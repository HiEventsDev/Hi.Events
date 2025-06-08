import OrganizerHomepage from "../OrganizerHomepage";
import {useLoaderData} from "react-router";
import {Organizer} from "../../../types.ts";
import {OrganizerNotFound} from "./OrganizerNotFound";

export const PublicOrganizer = () => {
    const loaderData = useLoaderData() as {
        organizer: Organizer | null;
        eventsData: any;
        isPastEvents: boolean;
    };

    if (!loaderData?.organizer) {
        return <OrganizerNotFound />;
    }

    return (
        <OrganizerHomepage
            organizer={loaderData.organizer}
            eventsData={loaderData.eventsData}
            isPastEvents={loaderData.isPastEvents}
        />
    );
};

export default PublicOrganizer;
