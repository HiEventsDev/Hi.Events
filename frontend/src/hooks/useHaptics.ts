import {useCallback} from "react";
import {isSsr} from "../utilites/helpers.ts";

type HapticPattern = "success" | "error" | "warning" | "tap";

const patterns: Record<HapticPattern, number | number[]> = {
    success: 20,
    tap: 10,
    warning: [30, 40, 30],
    error: [60, 60, 60],
};

export const useHaptics = () => {
    return useCallback((pattern: HapticPattern) => {
        if (isSsr()) return;
        if (typeof navigator === "undefined" || typeof navigator.vibrate !== "function") return;

        const stored = localStorage.getItem("scannerHapticsOn");
        const hapticsOn = stored === null ? true : stored === "true";
        if (!hapticsOn) return;

        try {
            navigator.vibrate(patterns[pattern]);
        } catch {
            // ignore
        }
    }, []);
};
