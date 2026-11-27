import {LoaderFunctionArgs} from "react-router";
import {AxiosError} from "axios";
import {WidgetLoaderData} from "../../../routeLoaders/widgetRouteLoader.ts";
import {loggableError} from "../../../ssr/loggableError.js";
import {getQueryClient} from "../../../utilites/ssrQueryClient.ts";
import {getSignupFormPublicQuery} from "../queries/useCrmSignupFormQueries.ts";

export const signupFormRouteLoader = async ({params}: LoaderFunctionArgs): Promise<WidgetLoaderData> => {
    try {
        await getQueryClient().ensureQueryData(getSignupFormPublicQuery(params.publicId));
        return {preset: null, promoCode: null, promoCodeValid: undefined, notFound: false};
    } catch (error) {
        const notFound = (error as AxiosError | undefined)?.response?.status === 404;
        if (!notFound) {
            console.error(loggableError(error));
        }
        return {preset: null, promoCode: null, promoCodeValid: undefined, notFound};
    }
};
