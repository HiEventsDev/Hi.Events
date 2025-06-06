import {LoaderFunctionArgs} from "react-router";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {getOrganizerPublicEventsQuery} from "../queries/useGetOrganizerEventsPublic.ts";
import {QueryFilterOperator} from "../types.ts";

export const publicOrganizerRouteLoader = async ({params}: LoaderFunctionArgs) => {
    const {organizerId} = params;

    if (!organizerId) {
        throw new Error('Organizer ID is required');
    }

    try {
        const organizer = await getQueryClient().fetchQuery(getOrganizerPublicQuery(organizerId));

        const upcomingEventsData = await getQueryClient().fetchQuery(
            getOrganizerPublicEventsQuery(organizerId, {
                pageNumber: 1,
                perPage: 25,
                sortBy: 'start_date',
                sortDirection: 'asc',
                filterFields: {
                    start_date: {
                        operator: QueryFilterOperator.GreaterThanOrEquals,
                        value: new Date().toISOString()
                    }
                }
            })
        );

        return {
            organizer,
            upcomingEventsData
        };
    } catch (error: any) {
        if (error?.response?.status === 404) {
            return {organizer: null, upcomingEventsData: null};
        }
        throw error;
    }
}
