import {Badge, Container, Group, Skeleton, Stack, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {Callout} from "../../../../../../components/common/Callout";
import {LicensedFeature, LicenceStatus} from "../../../../../../types.ts";
import {useGetAdminLicence} from "../../../../queries/useGetAdminLicence.ts";
import {GetLicenceLink} from "../../../GetLicenceLink";
import {DevelopmentLicenceSummary} from "../../../DevelopmentLicenceSummary";
import {licensedFeatureLabel} from "../../../../licenceNotice.ts";
import {useGetAdminLicenceSales} from "../../../../queries/useGetAdminLicenceSales.ts";
import {AdminLicence, LicencePlan, LicenceSalesPeriod} from "../../../../api/licence.client.ts";
import {CopyButton} from "../../../../../../components/common/CopyButton";
import dayjs from "dayjs";
import {formatDateWithLocale} from "../../../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../../../utilites/currency.ts";
import {getClientLocale, toIntlLocale} from "../../../../../../locales.ts";
import classes from "./Licence.module.scss";

const EXPIRY_WARNING_DAYS = 30;

const statusColor: Record<LicenceStatus, string> = {
    ACTIVE: 'green',
    GRACE: 'orange',
    LAPSED: 'red',
    NONE: 'gray',
    DEV: 'blue',
};

const statusLabel = (status: LicenceStatus): string => {
    switch (status) {
        case 'ACTIVE':
            return t`Active`;
        case 'GRACE':
            return t`Grace period`;
        case 'LAPSED':
            return t`Expired`;
        case 'DEV':
            return t`Development`;
        default:
            return t`No licence`;
    }
};

const planLabel = (plan: LicencePlan | null): string | null => {
    switch (plan) {
        case 'own_events':
            return t`Own events`;
        case 'platform':
            return t`Platform`;
        case 'cloud':
            return t`Hi.Events Cloud`;
        default:
            return null;
    }
};

const formatWholeEuros = (amount: number): string => new Intl.NumberFormat(toIntlLocale(getClientLocale()), {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(amount);

const formatDate = (date: string): string => formatDateWithLocale(date, 'shortDate', 'UTC');

const Row = ({label, value}: { label: string, value: string | null }) => (
    <div className={classes.row}>
        <Text size="sm" c="dimmed">{label}</Text>
        <Text size="sm" fw={500}>{value ?? '—'}</Text>
    </div>
);

const salesReport = (licence: AdminLicence, period: LicenceSalesPeriod): string => [
    'Hi.Events licence sales report',
    `Licence: ${licence.lid} (${licence.customer})`,
    `Plan: ${licence.plan}, covers EUR ${licence.covered_sales} a year`,
    `Licence year: ${period.starts_on} to ${period.ends_on}`,
    `Paid sales before tax, after refunds: ${period.sales.length === 0
        ? 'none'
        : period.sales.map(({currency, amount}) => `${currency} ${amount.toFixed(2)}`).join(', ')}`,
].join('\n');

const SalesPeriodRow = ({licence, period}: { licence: AdminLicence, period: LicenceSalesPeriod }) => (
    <div className={classes.row}>
        <Group gap="xs">
            <Text size="sm" c="dimmed">{t`${formatDate(period.starts_on)} to ${formatDate(period.ends_on)}`}</Text>
            {period.is_current && <Badge variant="light" size="sm">{t`Current year`}</Badge>}
        </Group>
        <Group gap={4} wrap="nowrap">
            <Text size="sm" fw={500}>
                {period.sales.length === 0
                    ? t`No sales`
                    : period.sales.map(({currency, amount}) => formatCurrency(amount, currency)).join(' · ')}
            </Text>
            <CopyButton value={salesReport(licence, period)} label={t`Copy sales report`}/>
        </Group>
    </div>
);

const LICENSED_FEATURES: LicensedFeature[] = ['white_label', 'seating', 'box_office', 'enterprise_sso'];

const LicenceFeatures = ({features}: { features: string[] }) => (
    <div className={classes.card} data-testid="admin-licence-features">
        <Text fw={600} mb="md">{t`Features`}</Text>
        {LICENSED_FEATURES.map((feature) => (
            <div key={feature} className={classes.row}>
                <Text size="sm">{licensedFeatureLabel(feature)}</Text>
                {features.includes(feature)
                    ? <Badge color="green" variant="light" size="sm">{t`Included`}</Badge>
                    : <Text size="sm" c="dimmed">{t`Not included`}</Text>}
            </div>
        ))}
    </div>
);

const LicenceSales = ({licence}: { licence: AdminLicence }) => {
    const {data: periods, isLoading} = useGetAdminLicenceSales();

    return (
        <div className={classes.card} data-testid="admin-licence-sales">
            <Text fw={600}>{t`Sales by licence year`}</Text>
            <Text size="sm" c="dimmed" mt={4} mb="md">
                {t`Paid sales before tax, after refunds, across every organizer on this install. At the end of each licence year, send that year's sales to licence@hi.events.`}
            </Text>
            {isLoading && <Skeleton height={80} radius="md"/>}
            {periods?.map((period) => <SalesPeriodRow key={period.starts_on} licence={licence} period={period}/>)}
        </div>
    );
};

const Licence = () => {
    const {data: licence, isLoading} = useGetAdminLicence();

    return (
        <Container size="md" p="xl">
            <Stack gap="lg">
                <Title order={1}>{t`Licence`}</Title>
                {isLoading && <Skeleton height={200} radius="md"/>}
                {licence && (
                    <>
                        {licence.invalid_reason && (
                            <Callout variant="warning" title={t`The licence key was rejected`}>
                                {licence.invalid_reason}
                            </Callout>
                        )}
                        {licence.status === 'NONE' && !licence.invalid_reason && (
                            <Callout variant="info">
                                {t`No licence key is set, so reserved seating, the box office and enterprise single sign-on can't be set up. To unlock them, set APP_LICENCE_KEY in the backend environment and redeploy the backend. With Docker Compose, use docker compose up -d, because docker compose restart doesn't reload the environment.`}{' '}
                                <GetLicenceLink source="app-licence-page"/>
                            </Callout>
                        )}
                        {licence.status === 'ACTIVE' && licence.expires_at && dayjs(licence.expires_at).diff(dayjs(), 'day') < EXPIRY_WARNING_DAYS && (
                            <Callout variant="warning">
                                {t`This licence expires on ${formatDateWithLocale(licence.expires_at, 'shortDate', 'UTC')}. Renew before then to avoid interruption.`}
                            </Callout>
                        )}
                        {licence.status === 'DEV' && (
                            <Callout variant="info">
                                <DevelopmentLicenceSummary/>{' '}
                                <GetLicenceLink source="app-licence-page"/>
                            </Callout>
                        )}
                        <div className={classes.card} data-testid="admin-licence-card">
                            <Group justify="space-between" mb="md">
                                <Text fw={600}>{t`Status`}</Text>
                                <Badge color={statusColor[licence.status]} variant="light" data-testid="admin-licence-status">
                                    {statusLabel(licence.status)}
                                </Badge>
                            </Group>
                            <Row label={t`Customer`} value={licence.customer}/>
                            <Row label={t`Plan`} value={planLabel(licence.plan)}/>
                            {licence.covered_sales !== null && (
                                <Row label={t`Covers`} value={t`${formatWholeEuros(licence.covered_sales)} of sales a year`}/>
                            )}
                            <Row label={t`Licence ID`} value={licence.lid}/>
                            <Row label={t`Issued`} value={licence.issued_at}/>
                            <Row label={t`Expires`} value={licence.expires_at}/>
                            <Row label={t`Grace period ends`} value={licence.grace_ends_at}/>
                        </div>
                        <LicenceFeatures features={licence.features}/>
                        {licence.covered_sales !== null && <LicenceSales licence={licence}/>}
                    </>
                )}
            </Stack>
        </Container>
    );
};

export default Licence;
