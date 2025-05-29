import {LoaderFunctionArgs} from "react-router";
import {queryClient} from "../utilites/queryClient.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {getOrganizerPublicEventsQuery} from "../queries/useGetOrganizerEventsPublic.ts";
import {QueryFilterOperator} from "../types.ts";

export const publicOrganizerRouteLoader = async ({params}: LoaderFunctionArgs) => {
    const {organizerId} = params;

    if (!organizerId) {
        throw new Error('Organizer ID is required');
    }

    try {
        const organizer = await queryClient.fetchQuery(getOrganizerPublicQuery(organizerId));

        const upcomingEventsData = await queryClient.fetchQuery(
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
            organizerId,
            organizer,
            upcomingEventsData
        };
    } catch (error: any) {
        if (error?.response?.status === 404) {
            return {organizerId, organizer: null, upcomingEventsData: null};
        }
        throw error;
    }
}
