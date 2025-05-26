import {queryClient} from "../utilites/queryClient.ts";
import {getOrganizerQuery} from "../queries/useGetOrganizer.ts";

export const publicOrganizerRouteLoader = async ({params}: { params: { organizerId: string } }) => {
    const {organizerId} = params;

    await queryClient.fetchQuery(getOrganizerQuery(organizerId));

    if (!organizerId) {
        throw new Error('Organizer ID is required');
    }

    return {
        organizerId,
    };
}
