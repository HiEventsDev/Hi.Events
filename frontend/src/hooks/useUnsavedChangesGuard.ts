import {useEffect, useRef} from "react";
import {useBlocker} from "react-router";
import {useWindowEvent} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {confirmationDialogAsync} from "../utilites/confirmationDialog.tsx";

export const useUnsavedChangesGuard = (hasUnsavedChanges: boolean | (() => boolean), message: string) => {
    const isDirty = () => typeof hasUnsavedChanges === 'function' ? hasUnsavedChanges() : hasUnsavedChanges;

    useWindowEvent('beforeunload', event => {
        if (isDirty()) {
            event.preventDefault();
        }
    });

    const blocker = useBlocker(({currentLocation, nextLocation}) =>
        currentLocation.pathname !== nextLocation.pathname && isDirty());

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
