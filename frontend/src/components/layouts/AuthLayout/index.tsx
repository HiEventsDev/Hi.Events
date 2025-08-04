import {Navigate, Outlet, useLocation} from "react-router";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {LanguageSwitcher} from "../../common/LanguageSwitcher";
import {
    IconBuildingStore,
    IconChartBar,
    IconClock,
    IconCreditCard,
    IconDeviceMobile,
    IconMessages,
    IconPalette,
    IconQrcode,
    IconTicket,
    IconWebhook
} from '@tabler/icons-react';
import {useMemo} from "react";
import { getConfig } from "../../../utilites/config.ts";
import {isHiEvents} from "../../../utilites/helpers.ts";

const RegisterFeatures = () => (
    <div className={classes.featureGrid}>
        <div className={classes.feature}>
            <IconClock size={16} className={classes.checkIcon}/>
            <div className={classes.featureText}>
                <h3>{t`Setup in Minutes`}</h3>
                <p>{t`Create and customize your event page instantly`}</p>
            </div>
        </div>

        <div className={classes.feature}>
            <IconCreditCard size={16} className={classes.checkIcon}/>
            <div className={classes.featureText}>
                <h3>{t`No Credit Card Required`}</h3>
                <p>{t`Get started for free, no subscription fees`}</p>
            </div>
        </div>

        <div className={classes.feature}>
            <IconTicket size={16} className={classes.checkIcon}/>
            <div className={classes.featureText}>
                <h3>{t`Sell More Than Tickets`}</h3>
                <p>{t`Products, merchandise, and flexible pricing options`}</p>
            </div>
        </div>
    </div>
);

const GenericFeatures = () => {
    const allFeatures = [
        {
            icon: IconChartBar,
            title: t`In-depth Analytics`,
            description: t`Track revenue, page views, and sales with detailed analytics and exportable reports`
        },
        {
            icon: IconTicket,
            title: t`Flexible Ticketing`,
            description: t`Support for tiered, donation-based, and product sales with customizable pricing and capacity`
        },
        {
            icon: IconDeviceMobile,
            title: t`Mobile Check-in`,
            description: t`QR code scanning with instant feedback and secure sharing for staff access`
        },
        {
            icon: IconBuildingStore,
            title: t`Sell Anything`,
            description: t`Sell merchandise alongside tickets with integrated tax and promo code support`
        },
        {
            icon: IconMessages,
            title: t`Attendee Management`,
            description: t`Message attendees, manage orders, and handle refunds all in one place`
        },
        {
            icon: IconQrcode,
            title: t`Smart Check-in`,
            description: t`Automated entry management with multiple check-in lists and real-time validation`
        },
        {
            icon: IconPalette,
            title: t`Match Your Brand`,
            description: t`Customize your event page and widget design to match your brand perfectly`
        },
        {
            icon: IconWebhook,
            title: t`Fully Integrated`,
            description: t`Connect with CRM and automate tasks using webhooks and integrations`
        }
    ];

    const selectedFeatures = useMemo(() => {
        const shuffled = [...allFeatures].sort(() => 0.5 - Math.random());
        return shuffled.slice(0, 3);
    }, []);

    return (
        <div className={classes.featureGrid}>
            {selectedFeatures.map((feature, index) => {
                const Icon = feature.icon;
                return (
                    <div key={index} className={classes.feature}>
                        <Icon size={16} className={classes.checkIcon}/>
                        <div className={classes.featureText}>
                            <h3>{feature.title}</h3>
                            <p>{feature.description}</p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};

const AuthLayout = () => {
    const me = useGetMe();
    const location = useLocation();
    const isRegisterPage = location.pathname === '/auth/register';

    if (me.isSuccess) {
        return <Navigate to={'/manage/events'}/>
    }

    return (
        <div className={classes.authLayout}>
            <div className={classes.splitLayout}>
                <div className={classes.leftPanel}>
                    <main className={classes.container}>
                        <div className={classes.logo}>
                            <img src={getConfig("VITE_APP_LOGO_DARK", "/logo-dark.svg")} alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}/>
                        </div>
                        <div className={classes.wrapper}>
                            <Outlet/>
                            {
                                /*
                                * (c) Hi.Events Ltd 2025
                                *
                                * PLEASE NOTE:
                                *
                                * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
                                *
                                * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENCE
                                *
                                * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
                                *
                                * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
                                */
                            }
                            {!isHiEvents() && <PoweredByFooter/>}
                            <div className={classes.languageSwitcher}>
                                <LanguageSwitcher/>
                            </div>
                        </div>
                    </main>
                </div>

                <div className={classes.rightPanel}>
                    <div className={classes.overlay}>
                        <div className={classes.content}>
                            {isRegisterPage ? <RegisterFeatures/> : <GenericFeatures/>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AuthLayout;
