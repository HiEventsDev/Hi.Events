import {Badge, Container, Group, Skeleton, Stack, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {Callout} from "../../../../../../components/common/Callout";
import {LicenceStatus} from "../../../../../../types.ts";
import {useGetAdminLicence} from "../../../../queries/useGetAdminLicence.ts";
import {GetLicenceLink} from "../../../GetLicenceLink";
import {DevelopmentLicenceSummary} from "../../../DevelopmentLicenceSummary";
import {licensedFeatureLabel} from "../../../../licenceNotice.ts";
import dayjs from "dayjs";
import {formatDateWithLocale} from "../../../../../../utilites/dates.ts";
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

const Row = ({label, value}: { label: string, value: string | null }) => (
    <div className={classes.row}>
        <Text size="sm" c="dimmed">{label}</Text>
        <Text size="sm" fw={500}>{value ?? '—'}</Text>
    </div>
);

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
                                {t`No licence key is set, so reserved seating and the box office are switched off. To unlock them, set APP_LICENCE_KEY in the backend environment and redeploy the backend. With Docker Compose, use docker compose up -d, because docker compose restart doesn't reload the environment.`}{' '}
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
                            <Row label={t`Plan`} value={licence.plan}/>
                            <Row label={t`Licence ID`} value={licence.lid}/>
                            <Row label={t`Issued`} value={licence.issued_at}/>
                            <Row label={t`Expires`} value={licence.expires_at}/>
                            <Row label={t`Grace period ends`} value={licence.grace_ends_at}/>
                            <div className={classes.row}>
                                <Text size="sm" c="dimmed">{t`Features`}</Text>
                                <Group gap="xs">
                                    {licence.features.length === 0 && <Text size="sm" fw={500}>—</Text>}
                                    {licence.features.map((feature) => (
                                        <Badge key={feature} variant="outline" color="gray" size="sm">
                                            {licensedFeatureLabel(feature)}
                                        </Badge>
                                    ))}
                                </Group>
                            </div>
                        </div>
                    </>
                )}
            </Stack>
        </Container>
    );
};

export default Licence;
