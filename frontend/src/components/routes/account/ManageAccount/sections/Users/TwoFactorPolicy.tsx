import {Button, Switch} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconShieldLock} from "@tabler/icons-react";
import {NavLink} from "react-router";
import {Card} from "../../../../../common/Card";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {useGetMe} from "../../../../../../queries/useGetMe.ts";
import {useUpdateAccountTwoFactorRequirement} from "../../../../../../mutations/useUpdateAccountTwoFactorRequirement.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {User} from "../../../../../../types.ts";
import classes from "./Users.module.scss";

interface TwoFactorPolicyProps {
    users: User[];
}

export const TwoFactorPolicy = ({users}: TwoFactorPolicyProps) => {
    const {data: account} = useGetAccount();
    const {data: me} = useGetMe();
    const updateRequirement = useUpdateAccountTwoFactorRequirement();

    if (!account) {
        return null;
    }

    const activeUsers = users.filter(user => user.status === 'ACTIVE');
    const enrolledCount = activeUsers.filter(user => user.two_factor_enabled).length;
    const notEnrolledCount = activeUsers.length - enrolledCount;
    const isRequired = !!account.require_two_factor_authentication;
    const selfEnrolled = !!me?.two_factor_enabled;

    const save = (required: boolean) => {
        updateRequirement.mutate({accountId: account.id!, required}, {
            onSuccess: () => showSuccess(required
                ? t`Two-factor authentication is now required`
                : t`Two-factor authentication is now optional`),
            onError: (error: any) => showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`),
        });
    };

    const handleToggle = (required: boolean) => {
        if (required && notEnrolledCount > 0) {
            confirmationDialog(
                notEnrolledCount === 1
                    ? t`1 member hasn't turned on two-factor authentication yet. They'll be asked to set it up before they can continue. Require it now?`
                    : t`${notEnrolledCount} members haven't turned on two-factor authentication yet. They'll be asked to set it up before they can continue. Require it now?`,
                () => save(true),
                {confirm: t`Require two-factor`},
            );
            return;
        }

        save(required);
    };

    return (
        <Card className={classes.policyCard}>
            <div className={classes.policyIcon}><IconShieldLock size={22} stroke={1.75}/></div>
            <div className={classes.policyContent}>
                <div className={classes.policyTitle}>{t`Require two-factor authentication`}</div>
                <p className={classes.policyText}>
                    {t`Everyone on this account must use an authenticator app to sign in. Members without it are asked to set it up before they can continue.`}
                </p>
                <div className={classes.policyStat}>
                    <span className={classes.policyMeter}>
                        <span
                            style={{width: `${activeUsers.length ? (enrolledCount / activeUsers.length) * 100 : 0}%`}}
                        />
                    </span>
                    <Trans>{enrolledCount} of {activeUsers.length} active members have it turned on</Trans>
                </div>
                {!selfEnrolled && !isRequired && (
                    <div className={classes.policyHint}>
                        {t`Turn on two-factor authentication for your own profile first.`}
                        <Button component={NavLink} to="/manage/profile/security" size="compact-xs" variant="light">
                            {t`Set it up`}
                        </Button>
                    </div>
                )}
            </div>
            <Switch
                size="md"
                checked={isRequired}
                disabled={updateRequirement.isPending || (!isRequired && !selfEnrolled)}
                onChange={(event) => handleToggle(event.currentTarget.checked)}
                aria-label={t`Require two-factor authentication`}
                data-testid="account-require-two-factor-switch"
            />
        </Card>
    );
};
