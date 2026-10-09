import {Loader, UnstyledButton} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconLogout, IconShieldLock} from "@tabler/icons-react";
import {Navigate} from "react-router";
import {useRef} from "react";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {authClient} from "../../../../api/auth.client.ts";
import {redirectToPreviousUrl} from "../../../../api/client.ts";
import {TwoFactorSetupFlow} from "../../../common/TwoFactor/TwoFactorSetupFlow.tsx";
import classes from "./RequiredTwoFactorSetup.module.scss";

const RequiredTwoFactorSetup = () => {
    const me = useGetMe();
    const account = useGetAccount();
    const setupStarted = useRef(false);

    if (me.isError) {
        return <Navigate to="/auth/login" replace/>;
    }

    if (me.isLoading || !me.data) {
        return <div className={classes.loading}><Loader size="sm"/></div>;
    }

    if (!me.data.two_factor_setup_required && !setupStarted.current) {
        return <Navigate to="/manage/events" replace/>;
    }

    setupStarted.current = true;

    const accountName = account.data?.name;

    const logout = async () => {
        await authClient.logout();
        localStorage.removeItem("token");
        window.location.href = "/auth/login";
    };

    return (
        <div className={classes.page}>
            <header className={classes.header}>
                <div className={classes.icon}><IconShieldLock size={26} stroke={1.75}/></div>
                <h2>{t`Secure your account`}</h2>
                <p>
                    {accountName
                        ? <Trans><strong>{accountName}</strong> requires two-factor authentication for everyone on the team. Set it up now to continue. It takes about a minute.</Trans>
                        : t`Your organisation requires two-factor authentication. Set it up now to continue. It takes about a minute.`}
                </p>
            </header>

            <TwoFactorSetupFlow email={me.data.email} onComplete={redirectToPreviousUrl}/>

            <UnstyledButton className={classes.logout} onClick={logout}>
                <IconLogout size={14}/>
                {t`Not now, log out`}
            </UnstyledButton>
        </div>
    );
};

export default RequiredTwoFactorSetup;
