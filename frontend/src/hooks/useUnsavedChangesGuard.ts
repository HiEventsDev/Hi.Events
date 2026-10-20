import {useEffect, useRef} from "react";
import {useBlocker} from "react-router";
import {useWindowEvent} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {confirmationDialogAsync} from "../utilites/confirmationDialog.tsx";

export const useUnsavedChangesGuard = (hasUnsavedChanges: boolean, message: string) => {
    useWindowEvent('beforeunload', event => {
        if (hasUnsavedChanges) {
            event.preventDefault();
        }
    });

    const blocker = useBlocker(({currentLocation, nextLocation}) =>
        hasUnsavedChanges && currentLocation.pathname !== nextLocation.pathname);

    const isConfirming = useRef(false);

    useEffect(() => {
        if (blocker.state !== 'blocked' || isConfirming.current) {
            return;
        }
        isConfirming.current = true;
        confirmationDialogAsync(message, {confirm: t`Leave`})
            .then(confirmed => {
                isConfirming.current = false;
                if (confirmed) {
                    blocker.proceed();
                } else {
                    blocker.reset();
                }
            });
    }, [blocker.state]);
};
