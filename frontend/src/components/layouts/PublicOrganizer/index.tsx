import OrganizerHomepage from "../OrganizerHomepage";
import {useLoaderData} from "react-router";
import {Organizer} from "../../../types.ts";
import {OrganizerNotFound} from "./OrganizerNotFound";

export const PublicOrganizer = () => {
    const loaderData = useLoaderData() as {
        organizer: Organizer | null;
        upcomingEventsData: any;
    };

    if (!loaderData?.organizer) {
        return <OrganizerNotFound />;
    }

    return (
        <OrganizerHomepage
            organizer={loaderData.organizer}
            upcomingEventsData={loaderData.upcomingEventsData}
            isPreview={false}
        />
    );
};

export default PublicOrganizer;
