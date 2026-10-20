import {useEffect, useState} from "react";
import {getConfig} from "../../../../utilites/config.ts";

const configuredBaseUrl = () => (getConfig('VITE_API_URL_CLIENT') ?? '/api').replace(/\/$/, '');

export const useApiBaseUrl = (): string => {
    const [baseUrl, setBaseUrl] = useState(configuredBaseUrl);

    useEffect(() => {
        try {
            setBaseUrl(new URL(configuredBaseUrl(), window.location.origin).toString().replace(/\/$/, ''));
        } catch {
            return;
        }
    }, []);

    return baseUrl;
};
