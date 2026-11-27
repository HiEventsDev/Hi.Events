import {CSSProperties} from "react";
import {Link, useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {Button, Skeleton} from "@mantine/core";
import {BarChart} from "@mantine/charts";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {getClientLocale, toDayjsLocale} from "../../../../../locales.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {useCrmAccess} from "../../../hooks/useCrmAccess.ts";
import {useCrmReady} from "../../../hooks/useCrmReady.ts";
import {useGetCrmOverview} from "../../../queries/useGetCrmOverview.ts";
import {useGetCrmRetention} from "../../../queries/useGetCrmRetention.ts";
import {useGetCrmLeadTime} from "../../../queries/useGetCrmLeadTime.ts";
import {CrmAudienceOverview, CrmLeadTime, CrmRetention} from "../../../types.ts";
import {formatCount, formatDecimal, formatPercent, monthLabel} from "../../../utilites/format.ts";
import {leadTimeBucketLabel} from "../../../utilites/labels.ts";
import {AudienceShell} from "../../AudienceShell";
import {LoadError} from "../../LoadError";
import shared from "../../Audience.module.scss";
import classes from "./Overview.module.scss";
import {Notice} from "../../Notice";

const Stats = ({overview}: { overview: CrmAudienceOverview }) => {
    const subscribedShare = formatPercent(overview.contacts_count === 0 ? 0 : overview.mailable_count / overview.contacts_count * 100);
    const newContacts = formatCount(overview.new_contacts_count);
    const repeatBuyers = formatCount(overview.repeat_buyers_count);
    const buyers = formatCount(overview.buyers_count);
    const stats = [
        {
            label: t`Contacts`,
            value: formatCount(overview.contacts_count),
            hint: t`${newContacts} new in the last 30 days`,
        },
        {
            label: t`Came back`,
            value: formatPercent(overview.repeat_rate),
            hint: t`${repeatBuyers} of ${buyers} booked 2+ events`,
        },
        {
            label: t`Subscribed`,
            value: formatCount(overview.mailable_count),
            hint: t`${subscribedShare} of your contacts`,
        },
        {
            label: t`Events per person`,
            value: formatDecimal(overview.average_events_per_buyer),
            hint: plural(overview.tickets_count, {one: '# ticket in total', other: '# tickets in total'}),
        },
    ];

    return (
        <div className={shared.stats}>
            {stats.map(stat => (
                <div key={stat.label} className={shared.stat}>
                    <span className={shared.statLabel}>{stat.label}</span>
                    <span className={shared.statValue}>{stat.value}</span>
                    <span className={shared.statHint}>{stat.hint}</span>
                </div>
            ))}
        </div>
    );
};

const BuyersChart = ({overview}: { overview: CrmAudienceOverview }) => {
    const locale = toDayjsLocale(getClientLocale());
    const data = overview.monthly_buyers.map(row => ({
        month: monthLabel(row.month, locale),
        returning_buyers: row.returning_buyers,
        new_buyers: row.new_buyers,
    }));

    return (
        <section className={shared.panel}>
            <div className={shared.panelHeader}>
                <h2 className={shared.panelTitle}>{t`New and returning buyers`}</h2>
                <span className={shared.panelHint}>{t`People who ordered each month`}</span>
            </div>
            <div className={shared.panelBody}>
                {data.length === 0 ? (
                    <div className={shared.empty}><p>{t`No orders in the last 12 months.`}</p></div>
                ) : (
                    <BarChart
                        h={300}
                        data={data}
                        dataKey="month"
                        type="stacked"
                        withLegend
                        legendProps={{verticalAlign: 'bottom'}}
                        tickLine="none"
                        gridAxis="y"
                        maxBarWidth={48}
                        yAxisProps={{width: 'auto', allowDecimals: false}}
                        xAxisProps={{minTickGap: 12}}
                        series={[
                            {name: 'returning_buyers', color: 'primary.6', label: t`Returning`},
                            {name: 'new_buyers', color: 'primary.2', label: t`New`},
                        ]}
                    />
                )}
            </div>
        </section>
    );
};

const Retention = ({retention}: { retention: CrmRetention }) => {
    const locale = toDayjsLocale(getClientLocale());
    const offsets = Array.from({length: Math.min(retention.max_offset, 6)}, (_, index) => index + 1);

    return (
        <section className={shared.panel}>
            <div className={shared.panelHeader}>
                <h2 className={shared.panelTitle}>{t`Who comes back, and when`}</h2>
                <span className={shared.panelHint}>{t`Share of each month's first-time buyers who ordered again`}</span>
            </div>
            <div className={shared.panelBody}>
                {retention.cohorts.length === 0 || offsets.length === 0 ? (
                    <div className={shared.empty}>
                        <p>{t`Once people have had time to book a second event, you'll see how many come back here.`}</p>
                    </div>
                ) : (
                    <div className={classes.retentionScroll}>
                        <table className={classes.retention}>
                            <thead>
                            <tr>
                                <th scope="col">{t`First order`}</th>
                                <th scope="col">{t`People`}</th>
                                {offsets.map(offset => (
                                    <th key={offset} scope="col">{t`+${offset} mo`}</th>
                                ))}
                            </tr>
                            </thead>
                            <tbody>
                            {retention.cohorts.map(cohort => (
                                <tr key={cohort.cohort}>
                                    <td>{monthLabel(cohort.cohort, locale)}</td>
                                    <td>{formatCount(cohort.size)}</td>
                                    {offsets.map(offset => {
                                        const month = cohort.months.find(entry => entry.offset === offset);

                                        return month ? (
                                            <td
                                                key={offset}
                                                className={classes.retentionCell}
                                                style={{'--retention': Math.min(month.percent * 1.6, 100)} as CSSProperties}
                                                data-strong={month.percent >= 40 || undefined}
                                            >
                                                {formatPercent(Math.round(month.percent))}
                                            </td>
                                        ) : <td key={offset}/>;
                                    })}
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
};

const LeadTimeBars = ({leadTime}: { leadTime: CrmLeadTime }) => (
    leadTime.tickets_count === 0 ? (
        <div className={shared.empty}><p>{t`Once tickets are sold, you'll see how far ahead of the event people buy.`}</p></div>
    ) : (
        <div className={shared.bars}>
            {leadTime.buckets.map(bucket => (
                <div key={bucket.bucket} className={shared.bar}>
                    <span className={shared.barLabel}>{leadTimeBucketLabel(bucket.bucket)}</span>
                    <span className={shared.barTrack}>
                        {bucket.percent > 0 && <span className={shared.barFill} style={{width: `${bucket.percent}%`}}/>}
                    </span>
                    <span className={shared.barValue}>{formatPercent(Math.round(bucket.percent))}</span>
                </div>
            ))}
        </div>
    )
);

interface OptInWarning {
    title: string;
    body: string;
    fixedInEvents: boolean;
    fixedInDefaults: boolean;
}

const marketingOptInWarning = (overview: CrmAudienceOverview): OptInWarning | null => {
    const notCollecting = overview.live_events_count - overview.live_events_collecting_opt_in;
    const liveCount = overview.live_events_count;

    if (liveCount > 0 && notCollecting === liveCount) {
        return {
            title: t`Your live events aren't asking buyers if they want to hear from you`,
            body: overview.marketing_opt_in_enabled
                ? t`Ticket buyers can only be emailed if they opt in at checkout. Turn on the marketing opt-in in each event's checkout settings.`
                : t`Ticket buyers can only be emailed if they opt in at checkout. Turn on the marketing opt-in in each event's checkout settings, and show it by default so new events ask every buyer.`,
            fixedInEvents: true,
            fixedInDefaults: !overview.marketing_opt_in_enabled,
        };
    }

    if (notCollecting > 0) {
        const notCollectingLabel = formatCount(notCollecting);
        const liveCountLabel = formatCount(liveCount);

        return {
            title: t`Live events that aren't asking buyers to opt in: ${notCollectingLabel} of ${liveCountLabel}`,
            body: t`Ticket buyers can only be emailed if they opt in at checkout. Turn on the marketing opt-in in those events' checkout settings.`,
            fixedInEvents: true,
            fixedInDefaults: false,
        };
    }

    if (!overview.marketing_opt_in_enabled) {
        return {
            title: t`New events won't ask buyers if they want to hear from you`,
            body: t`Ticket buyers can only be emailed if they opt in at checkout. Show the marketing opt-in by default so new events ask every buyer.`,
            fixedInEvents: false,
            fixedInDefaults: true,
        };
    }

    return null;
};

const AudienceOverview = () => {
    const {organizerId} = useParams();
    const {can} = useAccess();
    const {canChange} = useCrmAccess();
    const isReady = useCrmReady(organizerId);
    const overviewQuery = useGetCrmOverview(organizerId, isReady);
    const retentionQuery = useGetCrmRetention(organizerId, isReady);
    const leadTimeQuery = useGetCrmLeadTime(organizerId, isReady);
    const overview = overviewQuery.data;
    const optInWarning = overview ? marketingOptInWarning(overview) : null;
    const organizerPath = `/manage/organizer/${organizerId}`;
    const unlistedEvents = overview
        ? overview.live_events_count - overview.live_events_collecting_opt_in - overview.live_events_not_collecting_opt_in.length
        : 0;
    const isEmpty = overview?.contacts_count === 0;

    return (
        <AudienceShell>
            {overviewQuery.isLoading && (
                <>
                    <Skeleton height={92} radius="lg" mb="md"/>
                    <Skeleton height={260} radius="lg" mb="md"/>
                    <Skeleton height={300} radius="lg"/>
                </>
            )}

            {overviewQuery.isError && (
                <div className={shared.panel}>
                    <LoadError message={t`We couldn't load your audience overview.`} onRetry={() => overviewQuery.refetch()}/>
                </div>
            )}

            {overview && (
                <>
                    {optInWarning && (
                        <Notice title={optInWarning.title}>
                            {optInWarning.body}
                            {can('EDIT') && (
                                <span className={classes.noticeLinks}>
                                    {optInWarning.fixedInEvents && overview.live_events_not_collecting_opt_in.map(event => (
                                        <Link key={event.id} to={`/manage/event/${event.id}/settings/checkout`}>{event.title}</Link>
                                    ))}
                                    {optInWarning.fixedInEvents && (unlistedEvents > 0 || overview.live_events_not_collecting_opt_in.length === 0) && (
                                        <Link to={`${organizerPath}/events`}>
                                            {unlistedEvents > 0 && overview.live_events_not_collecting_opt_in.length > 0
                                                ? plural(unlistedEvents, {one: 'and # more event', other: 'and # more events'})
                                                : t`Go to your events`}
                                        </Link>
                                    )}
                                    {optInWarning.fixedInDefaults && <Link to={`${organizerPath}/settings/event-defaults`}>{t`Open event defaults`}</Link>}
                                </span>
                            )}
                        </Notice>
                    )}

                    {isEmpty && (
                        <div className={shared.panel}>
                            <div className={shared.empty}>
                                <img className={shared.emptyImage} src="/blank-slate/audience-overview.svg" alt=""/>
                                <p><strong>{t`Nobody in your audience yet`}</strong></p>
                                <p>
                                    {canChange
                                        ? t`People appear here as soon as they book, join a waitlist or sign up. You can also bring in a list you already have.`
                                        : t`People appear here as soon as they book, join a waitlist or sign up.`}
                                </p>
                                <div className={shared.emptyActions}>
                                    {canChange && (
                                        <>
                                            <Button variant="default" size="xs" component={Link} to={`${organizerPath}/audience/contacts`}>{t`Add or import contacts`}</Button>
                                            <Button variant="default" size="xs" component={Link} to={`${organizerPath}/audience/forms`}>{t`Create a signup form`}</Button>
                                        </>
                                    )}
                                    <Button variant="default" size="xs" component={Link} to={`${organizerPath}/events`}>{t`Go to your events`}</Button>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}

            {overview && !isEmpty && (
                <>
                    <Stats overview={overview}/>
                    <BuyersChart overview={overview}/>

                    <div className={shared.grid}>
                        {retentionQuery.data && <Retention retention={retentionQuery.data}/>}
                        {retentionQuery.isLoading && <Skeleton height={260} radius="lg"/>}
                        {retentionQuery.isError && (
                            <section className={shared.panel}>
                                <LoadError message={t`We couldn't load this chart.`} onRetry={() => retentionQuery.refetch()}/>
                            </section>
                        )}

                        <section className={shared.panel}>
                            <div className={shared.panelHeader}>
                                <h2 className={shared.panelTitle}>{t`When people buy`}</h2>
                                <span className={shared.panelHint}>{t`How far ahead of the event tickets are bought`}</span>
                            </div>
                            <div className={shared.panelBody}>
                                {leadTimeQuery.data && <LeadTimeBars leadTime={leadTimeQuery.data}/>}
                                {leadTimeQuery.isLoading && <Skeleton height={180} radius="md"/>}
                                {leadTimeQuery.isError && (
                                    <LoadError message={t`We couldn't load this chart.`} onRetry={() => leadTimeQuery.refetch()}/>
                                )}
                            </div>
                        </section>
                    </div>

                    {overview.revenue_concentration && overview.revenue_concentration.length > 0 && (
                        <section className={shared.panel}>
                            <div className={shared.panelHeader}>
                                <h2 className={shared.panelTitle}>{t`Where your revenue comes from`}</h2>
                            </div>
                            <div className={shared.panelBody}>
                                <div className={shared.bars}>
                                    {overview.revenue_concentration.map(row => {
                                        const topBuyersNet = formatCurrency(row.top_decile_net, row.currency);
                                        const totalNet = formatCurrency(row.total_net, row.currency);

                                        return (
                                            <div key={row.currency}>
                                                <p className={classes.barCaption}>
                                                    {t`Your top 10% of buyers brought in ${topBuyersNet} of ${totalNet}`}
                                                </p>
                                                <div className={classes.barWide}>
                                                    <span className={shared.barTrack}>
                                                        {row.top_decile_share > 0 && (
                                                            <span className={shared.barFill} style={{width: `${row.top_decile_share}%`}}/>
                                                        )}
                                                    </span>
                                                    <span className={shared.barValue}>{formatPercent(Math.round(row.top_decile_share))}</span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>
                    )}
                </>
            )}
        </AudienceShell>
    );
};

export default AudienceOverview;
