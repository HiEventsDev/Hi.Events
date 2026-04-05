import {LoaderFunctionArgs, redirect} from "react-router";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";

export const shortEventRedirectLoader = async ({params, request}: LoaderFunctionArgs) => {
    try {
        const url = new URL(request.url);
        const queryParams = new URLSearchParams(url.search);
        const searchString = queryParams.toString();

        const eventQuery = getEventPublicQuery(params.eventId);
        const event = await getQueryClient().fetchQuery(eventQuery);

        if (event && event.slug) {
            throw redirect(
                `/event/${event.id}/${event.slug}${searchString ? `?${searchString}` : ''}`
            );
        }

        // Fallback: redirect to event by ID only
        throw redirect(`/event/${params.eventId}/e${searchString ? `?${searchString}` : ''}`);
    } catch (error: any) {
        if (error instanceof Response) {
            throw error;
        }

        if (error?.response?.status === 404) {
            throw new Response("Event not found", {status: 404});
        }

        console.error(error);
        throw error;
    }
};
