import {useState} from "react";
import {Alert, Container, SegmentedControl, Skeleton, Stack, Table, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconInfoCircle} from "@tabler/icons-react";
import {useGetAdminEmailDeliveryStats} from "../../../../queries/useGetAdminEmailDeliveryStats";
import {AdminDashboardPeriod, AdminEmailDeliveryCategoryStats} from "../../../../api/admin.client";
import tableStyles from "../../../../styles/admin-table.module.scss";
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

                {isLoading ? (
                    <Skeleton height={320} radius="md"/>
                ) : (
                    <div className={tableStyles.tableWrapper}>
                        {categories.length === 0 ? (
                            <div className={classes.empty}>{t`No emails sent in this period`}</div>
                        ) : (
                            <div className={tableStyles.tableScroll}>
                                <Table className={tableStyles.table}>
                                    <Table.Thead>
                                        <Table.Tr>
                                            <Table.Th>{t`Email`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Sent`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Delivered`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Hard bounces`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Soft bounces`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Spam complaints`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Opened`}</Table.Th>
                                            <Table.Th className={classes.numeric}>{t`Clicked`}</Table.Th>
                                        </Table.Tr>
                                    </Table.Thead>
                                    <Table.Tbody>
                                        {categories.map((row) => (
                                            <Table.Tr key={row.category ?? 'other'}>
                                                <Table.Td>{categoryLabel(row.category)}</Table.Td>
                                                <Table.Td className={classes.numeric}>{formatNumber(row.sent)}</Table.Td>
                                                <Table.Td className={classes.numeric}>
                                                    {tracked ? formatRate(row.delivered, row.sent) : '-'}
                                                </Table.Td>
                                                <Table.Td
                                                    className={`${classes.numeric} ${tracked && row.sent > 0 && row.permanent_bounces / row.sent >= PERMANENT_BOUNCE_WARNING_RATE ? classes.warning : ''}`}
                                                >
                                                    {tracked ? formatNumber(row.permanent_bounces) : '-'}
                                                </Table.Td>
                                                <Table.Td className={classes.numeric}>
                                                    {tracked ? formatNumber(row.transient_bounces) : '-'}
                                                </Table.Td>
                                                <Table.Td className={classes.numeric}>
                                                    {tracked ? formatNumber(row.complaints) : '-'}
                                                </Table.Td>
                                                <Table.Td className={classes.numeric}>
                                                    {tracked ? formatRate(row.opened, row.delivered) : '-'}
                                                </Table.Td>
                                                <Table.Td className={classes.numeric}>
                                                    {tracked ? formatRate(row.clicked, row.delivered) : '-'}
                                                </Table.Td>
                                            </Table.Tr>
                                        ))}
                                    </Table.Tbody>
                                </Table>
                            </div>
                        )}
                    </div>
                )}
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
