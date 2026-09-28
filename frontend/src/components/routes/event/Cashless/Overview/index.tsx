import {t} from "@lingui/macro";
import {Alert, Button, Table, Text} from "@mantine/core";
import {IconLock, IconLockCheck} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {Card} from "../../../../common/Card";
import {KpiCell, KpiGrid} from "../../../../common/KpiGrid";
import {PeriodPreset, PeriodSelector} from "../../../../common/PeriodSelector";
import {CashlessRevenueChartCard} from "../../../../common/StatsCharts";
import {CashlessCloseModal} from "../../../../modals/CashlessCloseModal";
import {CashlessDisabledNotice} from "../CashlessDisabledNotice";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetCashlessSettings} from "../../../../../queries/useGetCashlessSettings.ts";
import {useGetCashlessSummary} from "../../../../../queries/useGetCashlessSummary.ts";
import {useGetCashlessStats} from "../../../../../queries/useGetCashlessStats.ts";
import {periodPresetToDateRange} from "../../../../../utilites/periodPreset.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatNumber} from "../../../../../utilites/helpers.ts";
import {prettyDate} from "../../../../../utilites/dates.ts";
import classes from "./CashlessOverview.module.scss";

const CashlessOverview = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: settings} = useGetCashlessSettings(eventId);
    const {data: summary, isLoading} = useGetCashlessSummary(eventId);
    const [closeModalOpen, {open: openCloseModal, close: closeCloseModal}] = useDisclosure(false);
    const [dateRange, setDateRange] = useState<PeriodPreset>('event_full');

    const {startDate, endDate} = useMemo(() => periodPresetToDateRange(dateRange, event), [dateRange, event]);
    const {data: dailyStats} = useGetCashlessStats(eventId, startDate, endDate);

    const currency = event?.currency ?? 'USD';
    const isClosed = !!settings?.cashless_closed_at;

    const money = (value?: number) => formatCurrency(value ?? 0, currency);

    return (
        <PageBody>
            <PageTitle
                subheading={t`How much has been loaded onto balances, spent at your sales points and what is still left.`}
            >
                {t`Cashless Overview`}
            </PageTitle>

            {settings && !settings.cashless_enabled && <CashlessDisabledNotice/>}

            {isClosed && settings?.cashless_closed_at && event && (
                <Alert icon={<IconLockCheck size={16}/>} color="gray" mb="md" data-testid="cashless-closed-notice">
                    {t`Cashless was closed on ${prettyDate(settings.cashless_closed_at, event.timezone)}. Every remaining balance was moved into your sales.`}
                </Alert>
            )}

            <KpiGrid>
                <KpiCell
                    label={t`Left in balances`}
                    value={money(summary?.outstanding_balance)}
                    isLoading={isLoading}
                    testId="cashless-overview-outstanding"
                />
                <KpiCell
                    label={t`Topped up`}
                    value={money((summary?.topped_up_online ?? 0) + (summary?.topped_up_staff ?? 0))}
                    isLoading={isLoading}
                    testId="cashless-overview-topped-up"
                />
                <KpiCell
                    label={t`Spent at sales points`}
                    value={money(summary?.spent)}
                    isLoading={isLoading}
                    testId="cashless-overview-spent"
                />
                <KpiCell
                    label={t`Refunded`}
                    value={money(summary?.refunded)}
                    isLoading={isLoading}
                    testId="cashless-overview-refunded"
                />
                <KpiCell
                    label={t`Moved to sales on closing`}
                    value={money(summary?.closed)}
                    isLoading={isLoading}
                    testId="cashless-overview-closed"
                />
                <KpiCell
                    label={t`Purchases`}
                    value={formatNumber(summary?.purchases_count ?? 0)}
                    isLoading={isLoading}
                    testId="cashless-overview-purchases"
                />
                <KpiCell
                    label={t`Online top-ups`}
                    value={money(summary?.topped_up_online)}
                    isLoading={isLoading}
                />
                <KpiCell
                    label={t`Top-ups at the event`}
                    value={money(summary?.topped_up_staff)}
                    isLoading={isLoading}
                />
                <KpiCell
                    label={t`Balances holding money`}
                    value={`${formatNumber(summary?.wallets_with_balance ?? 0)} / ${formatNumber(summary?.wallets_total ?? 0)}`}
                    isLoading={isLoading}
                />
            </KpiGrid>

            <div className={classes.section}>
                <PeriodSelector
                    value={dateRange}
                    onChange={setDateRange}
                    storageKey={`cashlessOverview.dateRange.${eventId}`}
                    event={event}
                />
            </div>

            <div className={classes.section}>
                <CashlessRevenueChartCard
                    dailyStats={dailyStats}
                    timezone={event?.timezone ?? 'UTC'}
                    dateRangeLabel=""
                    syncId="cashless-overview"
                    currency={currency}
                />
            </div>

            <div className={`${classes.section} ${classes.tables}`}>
                <Card>
                    <h3 className={classes.sectionTitle}>{t`Sales points`}</h3>
                    {summary && summary.sales_points.length === 0 ? (
                        <Text c="dimmed" size="sm">{t`No sales yet.`}</Text>
                    ) : (
                        <Table>
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Name`}</Table.Th>
                                    <Table.Th className={classes.numeric}>{t`Purchases`}</Table.Th>
                                    <Table.Th className={classes.numeric}>{t`Spent`}</Table.Th>
                                    <Table.Th className={classes.numeric}>{t`Top-ups`}</Table.Th>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {summary?.sales_points.map((salesPoint) => (
                                    <Table.Tr key={salesPoint.name}>
                                        <Table.Td>{salesPoint.name}</Table.Td>
                                        <Table.Td className={classes.numeric}>{formatNumber(salesPoint.purchases_count)}</Table.Td>
                                        <Table.Td className={classes.numeric}>{money(salesPoint.spent)}</Table.Td>
                                        <Table.Td className={classes.numeric}>{money(salesPoint.topped_up)}</Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    )}
                </Card>

                <Card>
                    <h3 className={classes.sectionTitle}>{t`Best sellers`}</h3>
                    {summary && summary.top_products.length === 0 ? (
                        <Text c="dimmed" size="sm">{t`No sales yet.`}</Text>
                    ) : (
                        <Table>
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{t`Product`}</Table.Th>
                                    <Table.Th className={classes.numeric}>{t`Quantity`}</Table.Th>
                                    <Table.Th className={classes.numeric}>{t`Total`}</Table.Th>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {summary?.top_products.map((product) => (
                                    <Table.Tr key={product.title}>
                                        <Table.Td>{product.title}</Table.Td>
                                        <Table.Td className={classes.numeric}>{formatNumber(product.quantity)}</Table.Td>
                                        <Table.Td className={classes.numeric}>{money(product.total)}</Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    )}
                </Card>
            </div>

            {settings?.cashless_enabled && !isClosed && summary && (
                <div className={classes.section}>
                    <Card>
                        <div className={classes.closure}>
                            <div className={classes.closureText}>
                                <h3 className={classes.sectionTitle}>{t`Close cashless`}</h3>
                                <Text size="sm" c="dimmed">
                                    {t`Once the event is over, close cashless to move the money left in balances into your total sales. Every balance is locked and this cannot be undone.`}
                                </Text>
                            </div>
                            <Button
                                color="red"
                                variant="light"
                                leftSection={<IconLock size={16}/>}
                                onClick={openCloseModal}
                                data-testid="cashless-close-button"
                            >
                                {t`Close cashless`}
                            </Button>
                        </div>
                    </Card>
                </div>
            )}

            {closeModalOpen && settings && summary && (
                <CashlessCloseModal
                    settings={settings}
                    summary={summary}
                    currency={currency}
                    onClose={closeCloseModal}
                />
            )}
        </PageBody>
    );
};

export default CashlessOverview;
