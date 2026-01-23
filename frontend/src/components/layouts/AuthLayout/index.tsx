import {Navigate, Outlet} from "react-router";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {LanguageSwitcher} from "../../common/LanguageSwitcher";
import {
    IconChartBar,
    IconCreditCard,
    IconDeviceMobile,
    IconPalette,
    IconQrcode,
    IconShieldCheck,
    IconSparkles,
    IconTicket,
    IconUsers,
} from '@tabler/icons-react';
import {useMemo} from "react";
import {getConfig} from "../../../utilites/config.ts";
import {isHiEvents} from "../../../utilites/helpers.ts";

const allFeatures = [
    {
        icon: IconTicket,
        title: t`Flexible Ticketing`,
        description: t`Paid, free, tiered pricing, and donation-based tickets`
    },
    {
        icon: IconQrcode,
        title: t`QR Code Check-in`,
        description: t`Mobile scanner with offline support and real-time tracking`
    },
    {
        icon: IconCreditCard,
        title: t`Instant Payouts`,
        description: t`Get paid immediately via Stripe Connect`
    },
    {
        icon: IconChartBar,
        title: t`Real-Time Analytics`,
        description: t`Track sales, revenue, and attendance with detailed reports`
    },
    {
        icon: IconPalette,
        title: t`Custom Branding`,
        description: t`Your logo, colors, and style on every page`
    },
    {
        icon: IconDeviceMobile,
        title: t`Mobile Optimized`,
        description: t`Beautiful checkout experience on any device`
    },
    {
        icon: IconUsers,
        title: t`Team Management`,
        description: t`Invite unlimited team members with custom roles`
    },
    {
        icon: IconShieldCheck,
        title: t`Data Ownership`,
        description: t`You own 100% of your attendee data, always`
    },
];

const FeaturePanel = () => {
    const selectedFeatures = useMemo(() => {
        const shuffled = [...allFeatures].sort(() => 0.5 - Math.random());
        return shuffled.slice(0, 4);
    }, []);

    return (
        <div className={classes.rightPanel}>
            <div className={classes.backgroundImage} />
            <div className={classes.backgroundOverlay} />
            <div className={classes.gridPattern} />
            <div className={`${classes.glowEffect} ${classes.glowTop}`} />
            <div className={`${classes.glowEffect} ${classes.glowBottom}`} />

            <div className={classes.overlay}>
                <div className={classes.content}>
                    <div className={classes.badge}>
                        <IconSparkles size={14} />
                        <span>{t`Event Management Platform`}</span>
                    </div>

                    <div className={classes.featureGrid}>
                        {selectedFeatures.map((feature, index) => {
                            const Icon = feature.icon;
                            return (
                                <div key={index} className={classes.feature}>
                                    <div className={classes.featureIcon}>
                                        <Icon size={18} />
                                    </div>
                                    <div className={classes.featureText}>
                                        <h3>{feature.title}</h3>
                                        <p>{feature.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
};

const AuthLayout = () => {
    const me = useGetMe();

    if (me.isSuccess) {
        return <Navigate to={'/manage/events'} />
    }

    return (
        <div className={classes.authLayout}>
            <div className={classes.splitLayout}>
                <div className={classes.leftPanel}>
                    <main className={classes.container}>
                        <div className={classes.logo}>
                            <img
                                src={getConfig("VITE_APP_LOGO_DARK", "/logos/hi-events-stacked-light.svg")}
                                alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}
                            />
                        </div>
                        <div className={classes.wrapper}>
                            <Outlet />
                            {/*
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
                             */}
                            {!isHiEvents() && <PoweredByFooter />}
                            <div className={classes.languageSwitcher}>
                                <LanguageSwitcher />
                            </div>
                        </div>
                    </main>
                </div>

                <FeaturePanel />
            </div>
        </div>
    );
};

export default AuthLayout;
