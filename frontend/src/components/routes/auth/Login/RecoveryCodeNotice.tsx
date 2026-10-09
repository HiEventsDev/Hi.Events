import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconAlertTriangle, IconKey} from "@tabler/icons-react";
import classNames from "classnames";
import classes from "./Login.module.scss";

interface RecoveryCodeNoticeProps {
    remaining: number;
    onManageCodes: () => void;
    onContinue: () => void;
}

export const RecoveryCodeNotice = ({remaining, onManageCodes, onContinue}: RecoveryCodeNoticeProps) => {
    const isLow = remaining <= 3;

    return (
        <div className={classes.challenge} data-testid="two-factor-recovery-notice">
            <header className={classes.challengeHeader}>
                <div className={classNames(classes.challengeIcon, {[classes.challengeIconWarning]: isLow})}>
                    {isLow ? <IconAlertTriangle size={26} stroke={1.75}/> : <IconKey size={26} stroke={1.75}/>}
                </div>
                <h2>{t`You're signed in`}</h2>
                <p>
                    {remaining === 0
                        ? t`That was your last recovery code. Generate a new set now so you don't get locked out.`
                        : remaining === 1
                            ? t`You used a recovery code. You have 1 recovery code left.`
                            : t`You used a recovery code. You have ${remaining} recovery codes left.`}
                </p>
            </header>
            <div className={classes.loginCard}>
                <p className={classes.noticeText}>
                    {t`If you've lost your phone, turn two-factor authentication off and on again from your profile to connect a new authenticator app.`}
                </p>
                <div className={classes.noticeActions}>
                    <Button color="secondary.5" fullWidth onClick={onManageCodes} data-testid="two-factor-manage-codes">
                        {t`Review security settings`}
                    </Button>
                    <Button variant="subtle" color="gray" fullWidth onClick={onContinue} data-testid="two-factor-continue">
                        {t`Continue to dashboard`}
                    </Button>
                </div>
            </div>
        </div>
    );
};
