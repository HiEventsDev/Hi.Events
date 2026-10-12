import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {useStartImpersonation} from "../mutations/useStartImpersonation";
import {showError, showSuccess} from "../utilites/notifications";
import {IdParam} from "../types";

export const useImpersonateUser = () => {
    const navigate = useNavigate();
    const startImpersonationMutation = useStartImpersonation();

    const impersonate = (userId: IdParam, accountId: IdParam) => {
        startImpersonationMutation.mutate({userId, accountId}, {
            onSuccess: (response) => {
                showSuccess(response.message || t`Impersonation started`);
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    navigate('/manage/events');
                }
            },
            onError: (error: any) => {
                showError(
                    error?.response?.data?.message ||
                    t`Failed to start impersonation. Please try again.`
                );
            }
        });
    };

    return {
        impersonate,
        isPending: startImpersonationMutation.isPending,
        pendingUserId: startImpersonationMutation.isPending ? startImpersonationMutation.variables?.userId : undefined,
    };
};
