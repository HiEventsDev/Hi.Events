import {EventStatus, QueryFilterOperator, QueryFilters} from "../types.ts";
import {useParams} from "react-router";

export const getEventQueryFilters = (searchParams: Partial<QueryFilters>) => {
    const {eventsState, organizerId} = useParams();
    return getEventQueryFiltersWithParams(searchParams, eventsState, organizerId);
};

export const getEventQueryFiltersWithParams = (
    searchParams: Partial<QueryFilters>, 
    eventsState?: string, 
    organizerId?: string
) => {
    let filter: Partial<QueryFilters> = {};
    if (eventsState === 'upcoming' || !eventsState) {
        filter = {
            additionalParams: {
                eventsStatus: 'upcoming',
            },
            filterFields: {}
        };
    } else if (eventsState === 'ended') {
        filter = {
            additionalParams: {
                eventsStatus: 'ended',
            },
            filterFields: {}
        };
    } else if (eventsState === 'archived') {
        filter = {
            filterFields: {
                status: {operator: QueryFilterOperator.Equals, value: EventStatus.ARCHIVED},
            }
        };
    }

    if (organizerId) {
        filter = {
            ...filter,
            filterFields: {
                organizer_id: {operator: QueryFilterOperator.Equals, value: organizerId},
                ...filter.filterFields
            }
        }
    }

    return {
        ...searchParams,
        ...filter,
    };
};
