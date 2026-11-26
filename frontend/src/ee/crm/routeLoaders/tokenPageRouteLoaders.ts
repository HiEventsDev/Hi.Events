import {LoaderFunctionArgs} from "react-router";
import {FetchQueryOptions} from "@tanstack/react-query";
import {loggableError} from "../../../ssr/loggableError.js";
import {apiErrorStatus} from "../../../utilites/apiErrors.ts";
import {getQueryClient} from "../../../utilites/ssrQueryClient.ts";
import {getSubscriptionConfirmationQuery} from "../queries/useCrmSignupFormQueries.ts";
import {getCrmUnsubscribeStatusQuery} from "../queries/useGetCrmUnsubscribeStatus.ts";

export interface TokenPageLoaderData {
    notFound: boolean;
}

const tokenPageLoader = <T>(query: (token: string | undefined) => FetchQueryOptions<T>) =>
    async ({params}: LoaderFunctionArgs): Promise<TokenPageLoaderData> => {
        try {
            await getQueryClient().ensureQueryData(query(params.token));
            return {notFound: false};
        } catch (error) {
            const notFound = apiErrorStatus(error) === 404;
            if (!notFound) {
                console.error(loggableError(error));
            }
            return {notFound};
        }
    };

export const subscriptionConfirmRouteLoader = tokenPageLoader(getSubscriptionConfirmationQuery);

export const campaignUnsubscribeRouteLoader = tokenPageLoader(getCrmUnsubscribeStatusQuery);
