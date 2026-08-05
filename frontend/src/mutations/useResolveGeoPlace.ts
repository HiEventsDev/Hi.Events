import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {locationClient} from "../api/location.client.ts";

export const useResolveGeoPlace = () => {
    return useMutation({
        mutationFn: ({organizerId, placeId, locale}: {organizerId: IdParam; placeId: string; locale?: string}) =>
            locationClient.placeDetails(organizerId, placeId, {locale}),
    });
};
