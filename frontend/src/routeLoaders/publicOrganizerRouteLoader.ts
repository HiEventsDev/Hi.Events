import {LoaderFunctionArgs, redirect} from "react-router";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {getOrganizerPublicEventsQuery} from "../queries/useGetOrganizerEventsPublic.ts";
import {EventStatus, QueryFilterOperator} from "../types.ts";

export const publicOrganizerRouteLoader = async ({params, request}: LoaderFunctionArgs) => {
    const {organizerId, organizerSlug} = params;
    const url = new URL(request.url);
    const queryParams = new URLSearchParams(url.search);
    const isPastEvents = url.pathname.endsWith('/past-events');
    const pageNumber = url.searchParams.get('page') ? parseInt(url.searchParams.get('page')!) : 1;

    if (!organizerId) {
        throw new Error('Organizer ID is required');
    }

    try {
        const organizer = await getQueryClient().fetchQuery(getOrganizerPublicQuery(organizerId));

        if (organizer && organizer.slug && organizerSlug !== organizer.slug) {
            const searchString = queryParams.toString();
            const pathSuffix = isPastEvents ? '/past-events' : '';
            throw redirect(
                `/events/${organizer.id}/${organizer.slug}${pathSuffix}${searchString ? `?${searchString}` : ''}`
            );
        }

        let filter = {};
        if (!isPastEvents) {
            filter = {
                additionalParams: {
                    eventsStatus: 'upcoming',
                },
                filterFields: {}
            };
        } else {
            filter = {
                filterFields: {
                    end_date: {operator: QueryFilterOperator.LessThanOrEquals, value: 'now'},
                    status: {operator: QueryFilterOperator.NotEquals, value: EventStatus.ARCHIVED},
                }
            };
        }

        const eventsData = await getQueryClient().fetchQuery(
            getOrganizerPublicEventsQuery(organizerId, {
                pageNumber: pageNumber,
                perPage: 30,
                sortBy: 'start_date',
                sortDirection: isPastEvents ? 'desc' : 'asc',
                ...filter
            })
        );

        return {
            organizer,
            eventsData,
            isPastEvents
        };
    } catch (error: any) {
        // Re-throw redirect responses so React Router can handle them
        if (error instanceof Response) {
            throw error;
        }

        if (error?.response?.status === 404) {
            return {organizer: null, eventsData: null, isPastEvents};
        }
        throw error;
    }
}
