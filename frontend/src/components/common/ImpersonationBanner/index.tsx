import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {Trans} from "@lingui/macro";
import {IconUserShield} from "@tabler/icons-react";
import {useStopImpersonation} from "../../../mutations/useStopImpersonation";
import {showError, showSuccess} from "../../../utilites/notifications";
import {useGetMe} from "../../../queries/useGetMe";
import classes from "./ImpersonationBanner.module.scss";
import {useNavigate} from "react-router";

const ImpersonationBanner = () => {
    const {data: user} = useGetMe();
    const stopImpersonationMutation = useStopImpersonation();
    const navigate = useNavigate();

    if (!user?.is_impersonating) {
        return null;
    }

    const handleStopImpersonation = () => {
        stopImpersonationMutation.mutate(undefined, {
            onSuccess: (response) => {
                showSuccess(response.message || t`Impersonation stopped`);
                navigate(response.redirect_url || '/admin/users');
            },
            onError: (error: any) => {
                showError(
                    error?.response?.data?.message ||
                    t`Failed to stop impersonation. Please try again.`
                );
            }
        });
    };

    return (
        <div className={classes.banner}>
            <div className={classes.content}>
                <IconUserShield size={24} className={classes.icon} />
                <span className={classes.text}>
                    <Trans>
                        You are impersonating <span className={classes.userName}>{user.full_name}</span> ({user.email})
                    </Trans>
                </span>
            </div>
            <Button
                variant="white"
                color="orange"
                size="sm"
                onClick={handleStopImpersonation}
                loading={stopImpersonationMutation.isPending}
                className={classes.button}
            >
                {t`Stop Impersonating`}
            </Button>
        </div>
    );
};

export default ImpersonationBanner;
