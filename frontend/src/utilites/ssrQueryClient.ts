import { QueryClient } from "@tanstack/react-query";
import {isSsr} from "./helpers.ts";
import {queryClient} from "./queryClient.ts";

let ssrQueryClient: QueryClient | null = null;

export function setSsrQueryClient(client: QueryClient | null) {
    ssrQueryClient = client;
}

export function getSsrQueryClient(): QueryClient | null {
    return ssrQueryClient;
}

export function getQueryClient(): QueryClient {
    if (isSsr() && ssrQueryClient) {
        return ssrQueryClient;
    }
    
    return queryClient;
}
