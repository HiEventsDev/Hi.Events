import {LoaderFunctionArgs} from "react-router";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {getOrganizerPublicEventsQuery} from "../queries/useGetOrganizerEventsPublic.ts";
import {QueryFilterOperator} from "../types.ts";

export const publicOrganizerRouteLoader = async ({params, request}: LoaderFunctionArgs) => {
    const {organizerId} = params;
    const url = new URL(request.url);
    const isPastEvents = url.pathname.endsWith('/past-events');

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
                sortDirection: isPastEvents ? 'desc' : 'asc',
                filterFields: {
                    start_date: {
                        operator: isPastEvents ? QueryFilterOperator.LessThan : QueryFilterOperator.GreaterThanOrEquals,
                        value: new Date().toISOString()
                    }
                }
            })
        );

        return {
            organizer,
            eventsData,
            isPastEvents
        };
    } catch (error: any) {
        if (error?.response?.status === 404) {
            return {organizer: null, eventsData: null, isPastEvents};
        }
        throw error;
    }
}
