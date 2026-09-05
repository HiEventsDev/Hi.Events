import {Navigate, Outlet} from "react-router";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {LanguageSwitcher} from "../../common/LanguageSwitcher";
import {useCallback, useEffect, useRef} from "react";
import {getConfig} from "../../../utilites/config.ts";
import {isHiEvents} from "../../../utilites/helpers.ts";
import {showInfo} from "../../../utilites/notifications.tsx";
import {captureUtmData} from "../../../utilites/utm.ts";

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
            <div className={classes.noise}/>
            <div className={classes.rings}/>

            <div className={classes.panelInner}>
                <h1 className={classes.heroTitle}>
                    <span className={classes.heroBold}>{t`Sell out your event.`}</span>
                    <span className={classes.heroLight}>{t`Keep the profit.`}</span>
                </h1>

                <div className={classes.ticketScene} aria-hidden="true">
                    <div className={classes.ticketGhost}/>
                    <div className={classes.ticket}>
                        <div className={classes.ticketInner}>
                            <div className={classes.ticketMain}>
                                <div className={classes.ticketTop}>
                                    <span>Admit One</span>
                                    <span>№ 000482</span>
                                </div>
                                <div className={classes.ticketTitle}>Dublin Jazz Festival</div>
                                <div className={classes.ticketMeta}>Sat, Aug 16 · 8:00 PM · Dublin</div>
                                <div className={classes.ticketFields}>
                                    <div className={classes.ticketField}>
                                        <span>Door</span>
                                        <strong>3</strong>
                                    </div>
                                    <div className={classes.ticketField}>
                                        <span>Seat</span>
                                        <strong>GA</strong>
                                    </div>
                                    <div className={classes.ticketField}>
                                        <span>Price</span>
                                        <strong>€89.00</strong>
                                    </div>
                                </div>
                                <div className={classes.barcode}/>
                            </div>
                            <div className={classes.ticketStub}>
                                <span className={classes.stubLabel}>Admit One</span>
                                <svg className={classes.stubQr} viewBox="0 0 25 25">
                                    <path fillRule="evenodd" d="M0 0h7v7H0zm1 1v5h5V1z"/>
                                    <rect x="2" y="2" width="3" height="3"/>
                                    <path fillRule="evenodd" d="M18 0h7v7h-7zm1 1v5h5V1z"/>
                                    <rect x="20" y="2" width="3" height="3"/>
                                    <path fillRule="evenodd" d="M0 18h7v7H0zm1 1v5h5v-5z"/>
                                    <rect x="2" y="20" width="3" height="3"/>
                                    <rect x="9" y="0" width="2" height="2"/>
                                    <rect x="13" y="2" width="2" height="2"/>
                                    <rect x="10" y="5" width="2" height="2"/>
                                    <rect x="15" y="5" width="2" height="2"/>
                                    <rect x="0" y="9" width="2" height="2"/>
                                    <rect x="4" y="10" width="2" height="2"/>
                                    <rect x="8" y="9" width="3" height="3"/>
                                    <rect x="13" y="10" width="2" height="2"/>
                                    <rect x="17" y="9" width="2" height="2"/>
                                    <rect x="21" y="10" width="2" height="2"/>
                                    <rect x="2" y="14" width="2" height="2"/>
                                    <rect x="7" y="13" width="2" height="2"/>
                                    <rect x="11" y="14" width="2" height="2"/>
                                    <rect x="15" y="13" width="3" height="3"/>
                                    <rect x="20" y="14" width="2" height="2"/>
                                    <rect x="9" y="18" width="2" height="2"/>
                                    <rect x="13" y="19" width="2" height="2"/>
                                    <rect x="18" y="18" width="2" height="2"/>
                                    <rect x="22" y="19" width="2" height="2"/>
                                    <rect x="10" y="22" width="3" height="2"/>
                                    <rect x="16" y="22" width="2" height="2"/>
                                </svg>
                                <span className={classes.stubSeat}>GA — €89</span>
                            </div>
                        </div>
                    </div>
                    <div className={classes.stamp}>Sold Out</div>
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
    const clickTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        captureUtmData();
    }, []);

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
                                src={getConfig("VITE_APP_LOGO_DARK", "/logos/hi-events-horizontal-light.svg")}
                                alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}
                            />
                        </div>
                        <div className={classes.formArea}>
                            <div className={classes.wrapper}>
                                <Outlet />
                            </div>
                        </div>
                        <div className={classes.panelFooter}>
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
