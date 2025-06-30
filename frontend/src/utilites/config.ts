import { ConfigKeys } from "../types.ts";
import { isSsr } from "./helpers.ts";
import process from "process";


export const clientBuildEnv: { [K in ConfigKeys]: string } = {
    'VITE_APP_PRIMARY_COLOR': import.meta.env.VITE_APP_PRIMARY_COLOR,
    'VITE_APP_SECONDARY_COLOR': import.meta.env.VITE_APP_SECONDARY_COLOR,
    'VITE_APP_NAME': import.meta.env.VITE_APP_NAME,
    'VITE_APP_FAVICON': import.meta.env.VITE_APP_FAVICON,
    'VITE_APP_LOGO_DARK': import.meta.env.VITE_APP_LOGO_DARK,
    'VITE_APP_LOGO_LIGHT': import.meta.env.VITE_APP_LOGO_LIGHT,
    'VITE_CHATWOOT_BASE_URL': import.meta.env.VITE_CHATWOOT_BASE_URL,
    'VITE_CHATWOOT_WEBSITE_TOKEN': import.meta.env.VITE_CHATWOOT_WEBSITE_TOKEN,
    'VITE_HIDE_ABOUT_LINK': import.meta.env.VITE_HIDE_ABOUT_LINK,
    'VITE_TOS_URL': import.meta.env.VITE_TOS_URL,
    'VITE_PRIVACY_URL': import.meta.env.VITE_PRIVACY_URL,
    'VITE_PLATFORM_SUPPORT_EMAIL': import.meta.env.VITE_PLATFORM_SUPPORT_EMAIL,
    'VITE_STRIPE_PUBLISHABLE_KEY': import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY,
    'VITE_I_HAVE_PURCHASED_A_LICENCE': import.meta.env.VITE_I_HAVE_PURCHASED_A_LICENCE,
    'VITE_FRONTEND_URL': import.meta.env.VITE_FRONTEND_URL,
    'VITE_DEFAULT_IMAGE_URL': import.meta.env.VITE_DEFAULT_IMAGE_URL,
    'VITE_API_URL_SERVER': import.meta.env.VITE_API_URL_SERVER,
    'VITE_API_URL_CLIENT': import.meta.env.VITE_API_URL_CLIENT
}

export const getConfig = (key: ConfigKeys, fallback?: string): string | undefined => {
    if (isSsr()) {
        const serverEnv = typeof process !== "undefined" && process.env ? process.env : {};
        return serverEnv[key] as string | undefined || fallback;
    }

    const clientEnv = typeof window !== "undefined" && window.hievents ? window.hievents : {};
    return clientEnv[key] || clientBuildEnv[key] || fallback;
};
