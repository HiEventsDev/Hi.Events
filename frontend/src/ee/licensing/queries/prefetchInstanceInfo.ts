import {QueryClient} from "@tanstack/react-query";
import {InstanceInfo, licenceClientPublic} from "../api/licence.client.ts";
import {GET_INSTANCE_INFO_QUERY_KEY} from "./useGetInstanceInfo.ts";
import {getSsrRequestContext} from "../../../utilites/ssrRequestContext.ts";

const SERVER_CACHE_MS = 60 * 1000;
const FAILURE_CACHE_MS = 10 * 1000;
const REQUEST_TIMEOUT_MS = 2000;
const SIMULATION_HEADER = 'x-hi-licence-simulation';

let cached: { info: InstanceInfo | null, expiresAt: number } | null = null;
let pending: Promise<InstanceInfo> | null = null;

const requestInstanceInfo = async (headers?: Record<string, string>): Promise<InstanceInfo> => {
    const response = await licenceClientPublic.getInstanceInfo({
        headers,
        signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });

    return response.data;
};

const fetchSharedInstanceInfo = (): Promise<InstanceInfo> => {
    if (cached && cached.expiresAt > Date.now()) {
        return cached.info
            ? Promise.resolve(cached.info)
            : Promise.reject(new Error('Instance info is temporarily unavailable'));
    }

    pending ??= requestInstanceInfo()
        .then((info) => {
            cached = {info, expiresAt: Date.now() + SERVER_CACHE_MS};
            return info;
        }, (error) => {
            cached = {info: null, expiresAt: Date.now() + FAILURE_CACHE_MS};
            throw error;
        })
        .finally(() => {
            pending = null;
        });

    return pending;
};

export const prefetchInstanceInfo = (queryClient: QueryClient) => {
    const simulation = getSsrRequestContext()?.licenceSimulation;

    return queryClient.prefetchQuery({
        queryKey: GET_INSTANCE_INFO_QUERY_KEY,
        queryFn: () => simulation
            ? requestInstanceInfo({[SIMULATION_HEADER]: simulation})
            : fetchSharedInstanceInfo(),
        retry: false,
    });
};
