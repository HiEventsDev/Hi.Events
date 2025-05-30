import {useMemo} from "react";

export enum Browser {
    Chrome = "Chromium",
    Firefox = "Firefox",
    Edge = "Edge",
    Brave = "Brave",
    Opera = "Opera",
    Chromium = "Chromium",
    Safari = "Safari",
    Unknown = "Unknown",
}

export const useBrowser = (): Browser => {
    return useMemo(() => {
        const ua = navigator.userAgent;

        if (/Firefox/.test(ua)) {
            return Browser.Firefox;
        }
        if (/Edg\//.test(ua)) {
            return Browser.Edge;
        }
        if (/OPR\//.test(ua)) {
            return Browser.Opera;
        }
        if (/Brave/.test(ua)) {
            return Browser.Brave;
        }
        if (/Chrome/.test(ua)) {
            if (navigator.userAgentData?.brands?.some(b => b.brand === "Chromium")) {
                return Browser.Chromium;
            }
            return Browser.Chrome;
        }
        if (/Safari/.test(ua)) {
            return Browser.Safari;
        }

        return Browser.Unknown;
    }, []);
};
