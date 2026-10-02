import {useRef, useState} from "react";
import {AxiosError} from "axios";
import {t} from "@lingui/macro";
import {confirmationDialogAsync} from "../../../../utilites/confirmationDialog.tsx";

export const useSeatMapVersion = (serverVersion: number | undefined, refetchVersion: () => Promise<number | undefined>) => {
    const savedVersion = useRef<number | null>(null);
    const [revision, setRevision] = useState(0);

    const current = () => savedVersion.current ?? serverVersion;

    const markSaved = (version: number | null) => {
        savedVersion.current = version;
    };

    const offerLatestVersion = async (error: unknown, sentVersion: number | undefined): Promise<boolean> => {
        if ((error as AxiosError).response?.status !== 409) {
            return false;
        }
        const latestVersion = await refetchVersion();
        if (latestVersion === undefined || latestVersion === sentVersion) {
            return false;
        }
        const confirmed = await confirmationDialogAsync(
            t`This seat map was changed somewhere else. Load the latest version? Your unsaved edits will be lost.`,
            {confirm: t`Load latest version`},
        );
        if (confirmed) {
            savedVersion.current = null;
            setRevision(value => value + 1);
        }
        return true;
    };

    return {revision, current, markSaved, offerLatestVersion};
};
