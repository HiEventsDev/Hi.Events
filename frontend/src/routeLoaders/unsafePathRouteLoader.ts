import {LoaderFunctionArgs} from "react-router";
import {hasUnsafePathSegment} from "../utilites/pathSafety.ts";

export const unsafePathRouteLoader = ({request}: LoaderFunctionArgs) => {
    if (hasUnsafePathSegment(new URL(request.url).pathname)) {
        throw new Response("Not Found", {status: 404});
    }

    return null;
};
