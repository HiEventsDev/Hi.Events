import {Navigate, Outlet} from "react-router";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {LanguageSwitcher} from "../../common/LanguageSwitcher";
import {IconBellRinging, IconUsersGroup} from "@tabler/icons-react";
import {useCallback, useRef} from "react";
import {getConfig} from "../../../utilites/config.ts";
import {isHiEvents} from "../../../utilites/helpers.ts";
import {showInfo} from "../../../utilites/notifications.tsx";

const tiers = [
    {name: "VIP Pass",    count: "87/100",  fill: 0.87},
    {name: "Early Bird",  count: "240/240", fill: 1.0},
    {name: "General",     count: "512/750", fill: 0.68},
];

const tickerFeatures = [
    t`Recurring events`,
    t`Instant Stripe payouts`,
    t`Custom branding`,
    t`QR code check-in`,
    t`Waitlist`,
    t`Promo codes`,
    t`Real-time analytics`,
    t`Email & scheduled messages`,
    t`Embeddable widget`,
    t`Affiliate program`,
    t`Team collaboration`,
    t`Custom questions`,
    t`Webhook integrations`,
    t`Full data ownership`,
    t`Multiple ticket types`,
    t`Capacity management`,
];

const FeaturePanel = () => {
    const tickerLoop = [...tickerFeatures, ...tickerFeatures];

    return (
        <div className={classes.rightPanel}>
            <div className={classes.noise} />
            <div className={classes.dotGrid} />

            <div className={classes.panelInner}>
                <div className={classes.headingBlock}>
                    <h1 className={classes.heroTitle}>
                        <span className={classes.heroBold}>{t`Sell out your event.`}</span>
                        <span className={classes.heroLight}>{t`Keep the profit.`}</span>
                    </h1>
                </div>

                <div className={classes.dashStage} aria-hidden="true">
                    {/* Secondary floating card — top right */}
                    <div className={`${classes.floatCard} ${classes.floatCardTop}`}>
                        <div className={classes.floatIcon}>
                            <IconUsersGroup size={16} strokeWidth={2.2}/>
                        </div>
                        <div className={classes.floatBody}>
                            <div className={classes.floatTitle}>{t`Waitlist triggered`}</div>
                            <div className={classes.floatSub}>{t`12 tickets offered`}</div>
                        </div>
                    </div>

                    {/* Main event dashboard card */}
                    <div className={classes.dashCard}>
                        <div className={classes.dashHeader}>
                            <div className={classes.dashHeaderLeft}>
                                <div className={classes.dashCover}/>
                                <div>
                                    <div className={classes.dashTitle}>Summer Synth Festival</div>
                                    <div className={classes.dashTitleSub}>Sat, Aug 16 · Berlin</div>
                                </div>
                            </div>
                            <div className={classes.dashBadge}>
                                <span className={classes.dashBadgeDot}/>
                                {t`Live`}
                            </div>
                        </div>

                        <div className={classes.dashStatRow}>
                            <div className={classes.dashStatBig}>$48,290</div>
                            <div className={classes.dashStatTrend}>↗ 12.4%</div>
                        </div>
                        <div className={classes.dashStatLabel}>{t`Revenue today`}</div>

                        <svg className={classes.dashChart} viewBox="0 0 280 48" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%"   style={{stopColor: "var(--mantine-color-primary-5)", stopOpacity: 0.35}}/>
                                    <stop offset="100%" style={{stopColor: "var(--mantine-color-primary-5)", stopOpacity: 0}}/>
                                </linearGradient>
                            </defs>
                            <path
                                className={classes.dashChartFill}
                                d="M 0 38 L 20 34 L 40 36 L 60 28 L 80 30 L 100 22 L 120 24 L 140 18 L 160 20 L 180 14 L 200 16 L 220 10 L 240 12 L 260 6 L 280 8 L 280 48 L 0 48 Z"
                            />
                            <path
                                className={classes.dashChartLine}
                                d="M 0 38 L 20 34 L 40 36 L 60 28 L 80 30 L 100 22 L 120 24 L 140 18 L 160 20 L 180 14 L 200 16 L 220 10 L 240 12 L 260 6 L 280 8"
                            />
                            <circle className={classes.dashChartDot} cx="280" cy="8" r="3.5"/>
                        </svg>

                        <div className={classes.dashTiers}>
                            {tiers.map((tier, i) => (
                                <div key={i} className={classes.dashTier}>
                                    <div className={classes.dashTierName}>{tier.name}</div>
                                    <div className={classes.dashTierBar}>
                                        <div
                                            className={classes.dashTierBarFill}
                                            style={{["--fill" as string]: tier.fill, animationDelay: `${0.6 + i * 0.15}s`}}
                                        />
                                    </div>
                                    <div className={classes.dashTierCount}>{tier.count}</div>
                                </div>
                            ))}
                        </div>

                        <div className={classes.dashFooter}>
                            <div className={classes.dashAvatars}>
                                <div className={`${classes.dashAvatar} ${classes.dashAvatar1}`}>MK</div>
                                <div className={`${classes.dashAvatar} ${classes.dashAvatar2}`}>JS</div>
                                <div className={`${classes.dashAvatar} ${classes.dashAvatar3}`}>AL</div>
                                <div className={`${classes.dashAvatar} ${classes.dashAvatar4}`}>+</div>
                            </div>
                            <div className={classes.dashFooterText}>839 attendees</div>
                        </div>
                    </div>

                    {/* Secondary floating card — bottom left */}
                    <div className={`${classes.floatCard} ${classes.floatCardBottom}`}>
                        <div className={classes.floatIcon}>
                            <IconBellRinging size={16} strokeWidth={2.2}/>
                        </div>
                        <div className={classes.floatBody}>
                            <div className={classes.floatTitle}>{t`Reminder scheduled`}</div>
                            <div className={classes.floatSub}>{t`Sending in 2d 4h`}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div className={classes.ticker} aria-hidden="true">
                <div className={classes.tickerTrack}>
                    {tickerLoop.map((item, i) => (
                        <span key={i} className={classes.tickerItem}>
                            {item}
                            <span className={classes.tickerDot}/>
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
};

const AuthLayout = () => {
    const me = useGetMe();
    const clickCountRef = useRef(0);
    const clickTimerRef = useRef<ReturnType<typeof setTimeout>>();

    const handleLogoClick = useCallback(() => {
        clickCountRef.current += 1;
        clearTimeout(clickTimerRef.current);
        clickTimerRef.current = setTimeout(() => { clickCountRef.current = 0; }, 2000);

        if (clickCountRef.current >= 5) {
            clickCountRef.current = 0;
            showInfo(`HiEvents v${__APP_VERSION__}`);
        }
    }, []);

    if (me.isSuccess) {
        return <Navigate to={'/manage/events'} />
    }

    return (
        <div className={classes.authLayout}>
            <div className={classes.splitLayout}>
                <div className={classes.leftPanel}>
                    <main className={classes.container}>
                        <div className={classes.logo} onClick={handleLogoClick} style={{cursor: 'pointer'}}>
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
