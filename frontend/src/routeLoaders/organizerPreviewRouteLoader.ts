import {LoaderFunctionArgs} from "react-router";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {getOrganizerPublicEventsQuery} from "../queries/useGetOrganizerEventsPublic.ts";

/**
 * Loader for the organizer preview page - does NOT redirect based on slug
 * This is used by the iframe preview in the homepage designer
 */
export const organizerPreviewRouteLoader = async ({params}: LoaderFunctionArgs) => {
    const {organizerId} = params;

    if (!organizerId) {
        throw new Error('Organizer ID is required');
    }

    try {
        const organizer = await getQueryClient().fetchQuery(getOrganizerPublicQuery(organizerId));

        const eventsData = await getQueryClient().fetchQuery(
            getOrganizerPublicEventsQuery(organizerId, {
                pageNumber: 1,
                perPage: 30,
                sortBy: 'start_date',
                sortDirection: 'asc',
                additionalParams: {
                    eventsStatus: 'upcoming',
                },
                filterFields: {}
            })
        );

        return {
            organizer,
            eventsData,
            isPastEvents: false
        };
    } catch (error: any) {
        if (error?.response?.status === 404) {
            return {organizer: null, eventsData: null, isPastEvents: false};
        }
        throw error;
    }
}
