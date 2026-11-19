import {useEffect, useState} from "react";
import {VisibilityState} from "@tanstack/react-table";

const STORAGE_KEY = 'audience-contacts-column-visibility';

const DEFAULT_VISIBILITY: VisibilityState = {attended_count: false, first_seen_at: false, phone: false, sms: false};

const readStoredColumns = (): VisibilityState => {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return {...DEFAULT_VISIBILITY, ...(stored ? JSON.parse(stored) : {})};
    } catch {
        return DEFAULT_VISIBILITY;
    }
};

const writeStoredColumns = (columns: VisibilityState) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(columns));
    } catch {
        return;
    }
};

export const useContactColumns = () => {
    const [visibility, setVisibility] = useState<VisibilityState>(DEFAULT_VISIBILITY);

    useEffect(() => setVisibility(readStoredColumns()), []);

    return {
        visibility,
        change: (next: VisibilityState) => {
            setVisibility(next);
            writeStoredColumns(next);
        },
    };
};
