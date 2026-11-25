import {plural, t} from "@lingui/macro";
import {IdParam} from "../../../../../types.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {CrmCampaign} from "../../../types.ts";
import {formatCount, formatPercent} from "../../../utilites/format.ts";
import {canAttributeCampaignRevenue} from "../../../utilites/labels.ts";
import audienceClasses from "../../Audience.module.scss";
import classes from "./Campaign.module.scss";
import {Recipients} from "./Recipients.tsx";

export const Report = ({organizerId, campaign, showRevenue}: { organizerId: IdParam, campaign: CrmCampaign, showRevenue: boolean }) => {
    const {stats} = campaign;
    const share = (value: number) => stats.sent === 0 ? '–' : formatPercent(Math.round(value / stats.sent * 100));
    const hasReports = campaign.delivery_reports_available !== false;
    const hasDeliveryData = hasReports && stats.delivered + stats.opened + stats.clicked + stats.bounced + stats.complained > 0;
    const isSms = campaign.channel === 'SMS';
    const showsAttribution = canAttributeCampaignRevenue(campaign);

    const attributionHint = isSms
        ? t`Used the early access link`
        : campaign.promo_code
            ? t`Used the early access link, or clicked and then ordered`
            : t`Clicked a link in the email, then ordered`;

    return (
        <div className={classes.report}>
            <div className={audienceClasses.stats}>
                <div className={audienceClasses.stat}>
                    <span className={audienceClasses.statLabel}>{t`Sent`}</span>
                    <span className={audienceClasses.statValue}>{formatCount(stats.sent)}</span>
                    <span className={audienceClasses.statHint}>
                        {stats.pending > 0
                            ? t`${formatCount(stats.pending)} still to go`
                            : t`${formatCount(stats.skipped + stats.failed - stats.undelivered)} skipped or failed`}
                    </span>
                </div>
                <div className={audienceClasses.stat}>
                    <span className={audienceClasses.statLabel}>{t`Delivered`}</span>
                    <span className={audienceClasses.statValue}>{hasDeliveryData ? share(stats.delivered) : '–'}</span>
                    <span className={audienceClasses.statHint}>
                        {!hasReports && t`Not available`}
                        {hasReports && !hasDeliveryData && t`No reports yet`}
                        {hasDeliveryData && isSms && t`${formatCount(stats.undelivered)} not delivered`}
                        {hasDeliveryData && !isSms && (stats.complained > 0
                            ? t`${formatCount(stats.bounced)} bounced, ${formatCount(stats.complained)} marked as spam`
                            : t`${formatCount(stats.bounced)} bounced`)}
                    </span>
                </div>
                {!isSms && <>
                <div className={audienceClasses.stat}>
                    <span className={audienceClasses.statLabel}>{t`Opened`}</span>
                    <span className={audienceClasses.statValue}>{hasDeliveryData ? share(stats.opened) : '–'}</span>
                    <span className={audienceClasses.statHint}>
                        {hasDeliveryData ? plural(stats.opened, {one: '# person', other: '# people'}) : ''}
                    </span>
                </div>
                <div className={audienceClasses.stat}>
                    <span className={audienceClasses.statLabel}>{t`Clicked`}</span>
                    <span className={audienceClasses.statValue}>{hasDeliveryData ? share(stats.clicked) : '–'}</span>
                    <span className={audienceClasses.statHint}>
                        {hasDeliveryData ? plural(stats.clicked, {one: '# person', other: '# people'}) : ''}
                    </span>
                </div>
                </>}
            </div>

            {!hasReports && (
                <p className={classes.reportNote} data-testid="campaign-no-delivery-reports">
                    {t`This installation's mail service doesn't send delivery reports, so delivered, opened, clicked and bounced figures aren't available, and addresses that bounce or report spam aren't removed for you.`}
                </p>
            )}

            {showRevenue && (
                <section className={classes.reportSection}>
                    <div className={classes.reportHeader}>
                        <h2>{t`Ticket sales from this campaign`}</h2>
                        <span>
                            {plural(campaign.attribution_window_days, {one: 'Orders within # day of sending', other: 'Orders within # days of sending'})}
                        </span>
                    </div>
                    <div>
                        {(campaign.revenue ?? []).length === 0 && (
                            <p className={classes.reportEmpty}>{t`No orders from recipients yet.`}</p>
                        )}
                        {(campaign.revenue ?? []).map(row => (
                            <div key={row.currency}>
                                {showsAttribution && (
                                    <div className={classes.revenueRow}>
                                        <span>
                                            {t`From the campaign`}
                                            <small>{attributionHint}</small>
                                        </span>
                                        <span>
                                            <strong>{formatCurrency(row.attributed_revenue, row.currency)}</strong>
                                            <small>{plural(row.attributed_orders, {one: '# order', other: '# orders'})}</small>
                                        </span>
                                    </div>
                                )}
                                <div className={classes.revenueRow}>
                                    <span>
                                        {showsAttribution ? t`Other orders from recipients` : t`Orders from recipients`}
                                        <small>
                                            {isSms
                                                ? t`Ordered after receiving the text`
                                                : showsAttribution
                                                    ? t`Ordered after receiving it, without a tracked click`
                                                    : t`Ordered after receiving it. Clicks aren't tracked on this installation, so orders can't be tied to the email itself.`}
                                        </small>
                                    </span>
                                    <span>
                                        <strong>{formatCurrency(row.influenced_revenue, row.currency)}</strong>
                                        <small>{plural(row.influenced_orders, {one: '# order', other: '# orders'})}</small>
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <Recipients organizerId={organizerId} campaign={campaign}/>
        </div>
    );
};
