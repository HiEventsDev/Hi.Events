import {t} from "@lingui/macro";

const CUTOFF_MINUTES = [0, 60, 120, 720, 1440, 2880, 10080];

export const cutoffLabel = (minutes: number): string => {
    if (minutes === 0) {
        return t`When the event starts`;
    }
    if (minutes % 1440 === 0) {
        const days = minutes / 1440;
        return days === 1 ? t`1 day before` : t`${days} days before`;
    }
    if (minutes % 60 === 0) {
        const hours = minutes / 60;
        return hours === 1 ? t`1 hour before` : t`${hours} hours before`;
    }
    return t`${minutes} minutes before`;
};

export const cutoffOptions = (current: number) => [...new Set([...CUTOFF_MINUTES, current])]
    .sort((a, b) => a - b)
    .map((minutes) => ({value: String(minutes), label: cutoffLabel(minutes)}));
