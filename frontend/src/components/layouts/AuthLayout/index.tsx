import {Navigate, Outlet} from "react-router";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {LanguageSwitcher} from "../../common/LanguageSwitcher";
import {IconCheck} from '@tabler/icons-react';

const AuthLayout = () => {
    const me = useGetMe();
    if (me.isSuccess) {
        return <Navigate to={'/manage/events'}/>
    }

    return (
        <div className={classes.authLayout}>
            <div className={classes.splitLayout}>
                <div className={classes.leftPanel}>
                    <header>
                        <div className={classes.languageSwitcher}>
                            <LanguageSwitcher/>
                        </div>
                    </header>

                    <main className={classes.container}>
                        <div className={classes.logo}>
                            <img src={'/logo-dark.svg'} alt={t`hi.events logo`}/>
                        </div>
                        <div className={classes.wrapper}>
                            <Outlet/>
                            <PoweredByFooter/>
                        </div>
                    </main>
                </div>

                <div className={classes.rightPanel}>
                    <div className={classes.overlay}>
                        <div className={classes.content}>
                            <div className={classes.featureGrid}>
                                <div className={classes.feature}>
                                    <IconCheck size={16} className={classes.checkIcon}/>
                                    <div className={classes.featureText}>
                                        <h3>{t`Setup in Minutes`}</h3>
                                        <p>{t`Create and customize your event page instantly`}</p>
                                    </div>
                                </div>

                                <div className={classes.feature}>
                                    <IconCheck size={16} className={classes.checkIcon}/>
                                    <div className={classes.featureText}>
                                        <h3>{t`No Credit Card Required`}</h3>
                                        <p>{t`Get started for free, no subscription fees`}</p>
                                    </div>
                                </div>

                                <div className={classes.feature}>
                                    <IconCheck size={16} className={classes.checkIcon}/>
                                    <div className={classes.featureText}>
                                        <h3>{t`Sell More Than Tickets`}</h3>
                                        <p>{t`Products, merchandise, and flexible pricing options`}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    );
};

export default AuthLayout;
