import {t} from "@lingui/macro";
import {IdParam} from "../../../types.ts";
import {firstApiError} from "../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useStartCrmSync} from "../mutations/useStartCrmSync.ts";
import {useGetCrmSyncStatus} from "../queries/useGetCrmSyncStatus.ts";

export const useResyncAudience = (organizerId: IdParam) => {
    const startSync = useStartCrmSync();
    const status = useGetCrmSyncStatus(organizerId, true).data?.status;

    const resync = () => confirmationDialog(
        t`Refresh your audience from all orders and check-ins?`,
        () => startSync.mutate({organizerId}, {
            onSuccess: () => showSuccess(t`Refreshing your audience in the background`),
            onError: error => showError(firstApiError(error, t`We couldn't refresh your audience. Please try again later.`)),
        }),
        {
            confirm: t`Refresh`,
            description: t`New orders already reach your audience on their own. Use this if something looks out of date. It runs in the background and can be started a few times an hour.`,
        },
    );

    return {resync, isBusy: startSync.isPending || status === 'SYNCING'};
};
