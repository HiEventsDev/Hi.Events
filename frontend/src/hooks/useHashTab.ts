import {useEffect, useState} from "react";
import {isSsr} from "../utilites/helpers.ts";

export const useHashTab = <T extends string>(allowed: readonly T[], defaultTab: T) => {
    const isAllowed = (value: string): value is T => (allowed as readonly string[]).includes(value);

    const [activeTab, setActiveTab] = useState<T>(() => {
        if (isSsr()) return defaultTab;
        const hash = window.location.hash.replace("#", "");
        return isAllowed(hash) ? hash : defaultTab;
    });

    useEffect(() => {
        if (isSsr()) return;
        if (window.location.hash !== `#${activeTab}`) {
            window.history.replaceState(null, "", `#${activeTab}`);
        }
    }, [activeTab]);

    useEffect(() => {
        if (isSsr()) return;
        const handleHashChange = () => {
            const hash = window.location.hash.replace("#", "");
            if (isAllowed(hash)) {
                setActiveTab(hash);
            }
        };
        window.addEventListener("hashchange", handleHashChange);
        return () => window.removeEventListener("hashchange", handleHashChange);
    }, []);

    return [activeTab, setActiveTab] as const;
};
