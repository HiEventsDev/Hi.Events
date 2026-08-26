import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {locationClient} from "../api/location.client.ts";

export const GEO_AUTOCOMPLETE_QUERY_KEY = "geoAutocomplete";

export const useGeoAutocomplete = (
    organizerId: IdParam,
    query: string,
    opts: {locale?: string; country?: string; enabled?: boolean} = {},
) => {
    const trimmed = query.trim();
    return useQuery({
        queryKey: [GEO_AUTOCOMPLETE_QUERY_KEY, organizerId, trimmed, opts.locale, opts.country],
        queryFn: () => locationClient.autocomplete(organizerId, trimmed, {locale: opts.locale, country: opts.country}),
        enabled: (opts.enabled ?? true) && organizerId !== undefined && organizerId !== null && trimmed.length >= 3,
        staleTime: 1000 * 30,
    });
};
