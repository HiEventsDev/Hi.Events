import {InternalAxiosRequestConfig} from "axios";
import {leavesItsEndpoint} from "../utilites/pathSafety.ts";

export const rejectUnsafeRequestPath = (config: InternalAxiosRequestConfig) => {
    if (leavesItsEndpoint(config.url ?? "")) {
        throw new Error("Refused an API request whose path leaves its endpoint");
    }

    return config;
};
