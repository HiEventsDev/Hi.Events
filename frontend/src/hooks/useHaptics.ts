import {useCallback} from "react";
import {isSsr} from "../utilites/helpers.ts";

type HapticPattern = "success" | "error" | "warning" | "tap";

const patterns: Record<HapticPattern, number | number[]> = {
    success: 20,
    tap: 10,
    warning: [30, 40, 30],
    error: [60, 60, 60],
};

/**
 * Vibration API wrapper with feature detection + user-preference gating via localStorage.
 * Call the returned `vibrate(pattern)` in response to a user gesture.
 *
 * Safari/iOS do not implement navigator.vibrate; the call is a no-op there rather than an error.
 */
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
            // Vibration can throw on some browsers when called without a user gesture; ignore.
        }
    }, []);
};
