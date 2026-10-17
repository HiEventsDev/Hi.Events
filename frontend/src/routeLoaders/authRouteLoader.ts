import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getSsoProvidersQuery} from "../queries/useGetSsoProviders.ts";

export const authRouteLoader = async () => {
    await getQueryClient().prefetchQuery(getSsoProvidersQuery());

    return null;
};
