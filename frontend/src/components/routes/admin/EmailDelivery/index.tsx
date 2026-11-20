import {useMemo, useState} from "react";
import {Alert, Container, SegmentedControl, Skeleton, Stack, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconInfoCircle} from "@tabler/icons-react";
import {useGetAdminEmailDeliveryStats} from "../../../../queries/useGetAdminEmailDeliveryStats";
import {AdminDashboardPeriod, AdminEmailDeliveryCategoryStats} from "../../../../api/admin.client";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellText, EmptyCell} from "../../../common/TanStackTable/cells.tsx";
import classes from "./EmailDelivery.module.scss";
import {getConfig} from "../../../../utilites/config";

const PERIODS: AdminDashboardPeriod[] = ['24h', '7d', '30d'];

const periodLabel = (period: AdminDashboardPeriod) => ({
    '24h': t`Last 24 hours`,
    '7d': t`Last 7 days`,
    '30d': t`Last 30 days`,
})[period];

const PERMANENT_BOUNCE_WARNING_RATE = 0.02;

const postmarkWebhookUrl = () => `${getConfig('VITE_API_URL_CLIENT')}/public/webhooks/email-delivery/postmark`;

const formatNumber = (value: number) => new Intl.NumberFormat().format(value);

const formatRate = (count: number, total: number) => total > 0
    ? new Intl.NumberFormat(undefined, {style: 'percent', maximumFractionDigits: 1}).format(count / total)
    : '-';

const categoryLabel = (category: string | null) => {
    if (!category) {
        return t`Other`;
    }

    const words = category.replace(/_/g, ' ');
    return words.charAt(0).toUpperCase() + words.slice(1);
};

const sumBy = (rows: AdminEmailDeliveryCategoryStats[], key: keyof Omit<AdminEmailDeliveryCategoryStats, 'category'>) =>
    rows.reduce((total, row) => total + row[key], 0);

const EmailDelivery = () => {
    const [period, setPeriod] = useState<AdminDashboardPeriod>('7d');
    const {data, isLoading} = useGetAdminEmailDeliveryStats(period);

    const categories = data?.categories ?? [];
    const tracked = data?.delivery_webhook_configured ?? false;
    const sent = sumBy(categories, 'sent');
    const delivered = sumBy(categories, 'delivered');
    const permanentBounces = sumBy(categories, 'permanent_bounces');

    const columns = useMemo<TanStackTableColumn<AdminEmailDeliveryCategoryStats>[]>(() => {
        const trackedColumn = (
            id: string,
            header: string,
            value: (row: AdminEmailDeliveryCategoryStats) => string,
        ): TanStackTableColumn<AdminEmailDeliveryCategoryStats> => ({
            id,
            header,
            meta: {align: 'right'},
            cell: ({row: {original: row}}) => tracked ? value(row) : <EmptyCell/>,
        });

        return [
            {
                id: 'category',
                header: t`Email`,
                meta: {sticky: 'left'},
                cell: ({row: {original: row}}) => <CellText strong>{categoryLabel(row.category)}</CellText>,
            },
            {
                id: 'sent',
                header: t`Sent`,
                meta: {align: 'right'},
                cell: ({row: {original: row}}) => formatNumber(row.sent),
            },
            trackedColumn('delivered', t`Delivered`, row => formatRate(row.delivered, row.sent)),
            {
                id: 'permanent_bounces',
                header: t`Hard bounces`,
                meta: {align: 'right'},
                cell: ({row: {original: row}}) => {
                    if (!tracked) {
                        return <EmptyCell/>;
                    }
                    const isHigh = row.sent > 0 && row.permanent_bounces / row.sent >= PERMANENT_BOUNCE_WARNING_RATE;

                    return (
                        <span className={isHigh ? classes.warning : undefined}>
                            {formatNumber(row.permanent_bounces)}
                        </span>
                    );
                },
            },
            trackedColumn('transient_bounces', t`Soft bounces`, row => formatNumber(row.transient_bounces)),
            trackedColumn('complaints', t`Spam complaints`, row => formatNumber(row.complaints)),
            trackedColumn('opened', t`Opened`, row => formatRate(row.opened, row.delivered)),
            trackedColumn('clicked', t`Clicked`, row => formatRate(row.clicked, row.delivered)),
        ];
    }, [tracked]);

    return (
        <Container size="xl" className={classes.container}>
            <Stack gap="lg">
                <div className={classes.header}>
                    <div>
                        <Title order={1}>{t`Email Delivery`}</Title>
                        {data && (
                            <div className={classes.totals}>
                                <span><strong>{formatNumber(sent)}</strong> {t`sent`}</span>
                                {tracked && (
                                    <>
                                        <span><strong>{formatRate(delivered, sent)}</strong> {t`delivered`}</span>
                                        <span><strong>{formatRate(permanentBounces, sent)}</strong> {t`hard bounces`}</span>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                    <SegmentedControl
                        size="xs"
                        value={period}
                        onChange={(value) => setPeriod(value as AdminDashboardPeriod)}
                        data={PERIODS.map((p) => ({value: p, label: periodLabel(p)}))}
                        data-testid="admin-email-delivery-period"
                    />
                </div>

                {data && !tracked && (
                    <Alert icon={<IconInfoCircle/>} color="gray">
                        {t`Sent emails are recorded, but delivery, bounce, open and click data needs a delivery webhook. Set POSTMARK_WEBHOOK_SECRET and point your Postmark streams at ${postmarkWebhookUrl()}, using the secret as the basic auth password.`}
                    </Alert>
                )}

                {isLoading && <Skeleton height={320} radius="md"/>}

                {!isLoading && categories.length === 0 && (
                    <div className={classes.empty}>{t`No emails sent in this period`}</div>
                )}

                {!isLoading && categories.length > 0 && <TanStackTable data={categories} columns={columns}/>}

                {tracked && (
                    <Text size="xs" c="dimmed">
                        {t`Open and click rates are a share of delivered emails and only cover streams with tracking turned on. Hard bounces are highlighted at 2% or more.`}
                    </Text>
                )}
            </Stack>
        </Container>
    );
};

export default EmailDelivery;
