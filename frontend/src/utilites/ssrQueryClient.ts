import {QueryClient} from "@tanstack/react-query";
import {isSsr} from "./helpers.ts";
import {queryClient} from "./queryClient.ts";
import {getSsrRequestContext} from "./ssrRequestContext.ts";

export function getQueryClient(): QueryClient {
    if (isSsr()) {
        const ssrQueryClient = getSsrRequestContext()?.queryClient;
        if (ssrQueryClient) {
            return ssrQueryClient;
        }
    }

    return queryClient;
}
