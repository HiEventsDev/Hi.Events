import { ConfigKeys } from "../types.ts";
import { isSsr } from "./helpers.ts";
import process from "process";

export const getConfig = (key: ConfigKeys): string | undefined => {
    if (isSsr()) {
        const serverEnv = typeof process !== "undefined" && process.env ? process.env : {};
        return serverEnv[key] as string | undefined;
    }

    const clientEnv = typeof window !== "undefined" && window.hievents ? window.hievents : {};
    return clientEnv[key];
};
