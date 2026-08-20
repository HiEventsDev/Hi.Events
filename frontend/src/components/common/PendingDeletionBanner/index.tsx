import {Button} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconTrash} from "@tabler/icons-react";
import {useNavigate} from "react-router";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from "./PendingDeletionBanner.module.scss";

const PendingDeletionBanner = () => {
    const {data: account} = useGetAccount();
    const navigate = useNavigate();

    if (!account?.deletion_request) {
        return null;
    }

    const scheduledDate = prettyDate(account.deletion_request.scheduled_deletion_at, account.timezone || 'UTC');

    return (
        <div className={classes.banner}>
            <div className={classes.content}>
                <IconTrash size={24} className={classes.icon}/>
                <span className={classes.text}>
                    <Trans>
                        This account is scheduled for deletion on <span className={classes.date}>{scheduledDate}</span>
                    </Trans>
                </span>
            </div>
            <Button
                variant="white"
                color="red"
                size="sm"
                onClick={() => navigate('/account/danger-zone')}
                className={classes.button}
                data-testid="pending-deletion-banner-button"
            >
                {t`Cancel deletion`}
            </Button>
        </div>
    );
};

export default PendingDeletionBanner;
