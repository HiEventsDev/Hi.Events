import {Container, Title, Stack, Card, Text, Group, Button, Badge, Skeleton, Select} from "@mantine/core";
import {t} from "@lingui/macro";
import {useParams, useNavigate} from "react-router";
import {useGetAdminAccount} from "../../../../../queries/useGetAdminAccount";
import {useGetAllConfigurations} from "../../../../../queries/useGetAllConfigurations";
import {useAssignConfiguration} from "../../../../../mutations/useAssignConfiguration";
import {IconArrowLeft, IconCalendar, IconWorld, IconEdit, IconBuildingBank, IconUsers} from "@tabler/icons-react";
import {useState} from "react";
import {EditAccountVatSettingsModal} from "../../../../modals/EditAccountVatSettingsModal";
import {showSuccess, showError} from "../../../../../utilites/notifications";
import classes from "./AccountDetail.module.scss";

const AccountDetail = () => {
    const {accountId} = useParams();
    const navigate = useNavigate();
    const {data: accountData, isLoading} = useGetAdminAccount(accountId);
    const {data: configurationsData} = useGetAllConfigurations();
    const assignConfigMutation = useAssignConfiguration(accountId!);
    const [showVatModal, setShowVatModal] = useState(false);

    const account = accountData?.data;
    const configurations = configurationsData?.data || [];

    const formatDate = (dateString?: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    };

    const handleConfigurationChange = (value: string | null) => {
        if (!value) return;

        assignConfigMutation.mutate(
            {configuration_id: parseInt(value, 10)},
            {
                onSuccess: () => showSuccess(t`Configuration assigned successfully`),
                onError: () => showError(t`Failed to assign configuration`),
            }
        );
    };

    if (isLoading) {
        return (
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Skeleton height={40} width={200} />
                    <Skeleton height={200} radius="md" />
                    <Skeleton height={150} radius="md" />
                    <Skeleton height={150} radius="md" />
                </Stack>
            </Container>
        );
    }

    if (!account) {
        return (
            <Container size="xl" p="xl">
                <Text c="dimmed">{t`Account not found`}</Text>
            </Container>
        );
    }

    const configOptions = configurations.map((config) => ({
        value: String(config.id),
        label: config.is_system_default ? `${config.name} (${t`Default`})` : config.name,
    }));

    return (
        <>
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Group>
                        <Button
                            variant="subtle"
                            leftSection={<IconArrowLeft size={16} />}
                            onClick={() => navigate('/admin/accounts')}
                        >
                            {t`Back to Accounts`}
                        </Button>
                    </Group>

                    <Title order={1}>{account.name}</Title>

                    <Card className={classes.accountCard}>
                        <Stack gap="md">
                            <Group justify="space-between">
                                <Text size="lg" fw={600}>{t`Account Information`}</Text>
                            </Group>

                            <div className={classes.infoGrid}>
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Email`}</Text>
                                    <Text size="sm">{account.email}</Text>
                                </div>
                                <div className={classes.infoItem}>
                                    <Text size="xs" c="dimmed">{t`Created`}</Text>
                                    <Group gap="xs">
                                        <IconCalendar size={14} />
                                        <Text size="sm">{formatDate(account.created_at)}</Text>
                                    </Group>
                                </div>
                                {account.timezone && (
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Timezone`}</Text>
                                        <Group gap="xs">
                                            <IconWorld size={14} />
                                            <Text size="sm">{account.timezone}</Text>
                                        </Group>
                                    </div>
                                )}
                                {account.currency_code && (
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Currency`}</Text>
                                        <Badge variant="light">{account.currency_code}</Badge>
                                    </div>
                                )}
                            </div>

                            <div className={classes.statsGrid}>
                                <div className={classes.statItem}>
                                    <IconBuildingBank size={24} />
                                    <Stack gap={2}>
                                        <Text size="xs" c="dimmed">{t`Events`}</Text>
                                        <Text size="xl" fw={600}>{account.events_count}</Text>
                                    </Stack>
                                </div>
                                <div className={classes.statItem}>
                                    <IconUsers size={24} />
                                    <Stack gap={2}>
                                        <Text size="xs" c="dimmed">{t`Users`}</Text>
                                        <Text size="xl" fw={600}>{account.users_count}</Text>
                                    </Stack>
                                </div>
                            </div>
                        </Stack>
                    </Card>

                    <Card className={classes.accountCard}>
                        <Stack gap="md">
                            <Text size="lg" fw={600}>{t`Application Fees Configuration`}</Text>

                            <Select
                                label={t`Assigned Configuration`}
                                description={t`Select which fee configuration applies to this account. Fees are always in USD.`}
                                data={configOptions}
                                value={account.configuration?.id ? String(account.configuration.id) : null}
                                onChange={handleConfigurationChange}
                                placeholder={t`Select a configuration`}
                                disabled={assignConfigMutation.isPending}
                            />

                            {account.configuration && (
                                <div className={classes.infoGrid}>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Fixed Fee (USD)`}</Text>
                                        <Text size="sm" fw={500}>
                                            ${account.configuration.application_fees?.fixed || 0}
                                        </Text>
                                    </div>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Percentage Fee`}</Text>
                                        <Text size="sm" fw={500}>
                                            {account.configuration.application_fees?.percentage || 0}%
                                        </Text>
                                    </div>
                                </div>
                            )}

                            <Text size="xs" c="dimmed">
                                {t`To edit configurations, go to the Configurations section in the admin menu.`}
                            </Text>
                        </Stack>
                    </Card>

                    <Card className={classes.accountCard}>
                        <Stack gap="md">
                            <Group justify="space-between">
                                <Text size="lg" fw={600}>{t`VAT Settings`}</Text>
                                <Button
                                    variant="light"
                                    size="xs"
                                    leftSection={<IconEdit size={14} />}
                                    onClick={() => setShowVatModal(true)}
                                >
                                    {t`Edit`}
                                </Button>
                            </Group>

                            {account.vat_setting ? (
                                <div className={classes.infoGrid}>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`VAT Registered`}</Text>
                                        <Badge color={account.vat_setting.vat_registered ? 'green' : 'gray'}>
                                            {account.vat_setting.vat_registered ? t`Yes` : t`No`}
                                        </Badge>
                                    </div>
                                    {account.vat_setting.vat_registered && (
                                        <>
                                            <div className={classes.infoItem}>
                                                <Text size="xs" c="dimmed">{t`VAT Number`}</Text>
                                                <Text size="sm">{account.vat_setting.vat_number || '-'}</Text>
                                            </div>
                                            <div className={classes.infoItem}>
                                                <Text size="xs" c="dimmed">{t`Validated`}</Text>
                                                <Badge color={account.vat_setting.vat_validated ? 'green' : 'red'}>
                                                    {account.vat_setting.vat_validated ? t`Valid` : t`Invalid`}
                                                </Badge>
                                            </div>
                                            {account.vat_setting.business_name && (
                                                <div className={classes.infoItem}>
                                                    <Text size="xs" c="dimmed">{t`Business Name`}</Text>
                                                    <Text size="sm">{account.vat_setting.business_name}</Text>
                                                </div>
                                            )}
                                            {account.vat_setting.vat_country_code && (
                                                <div className={classes.infoItem}>
                                                    <Text size="xs" c="dimmed">{t`VAT Country`}</Text>
                                                    <Text size="sm">{account.vat_setting.vat_country_code}</Text>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            ) : (
                                <Text size="sm" c="dimmed">{t`No VAT settings configured`}</Text>
                            )}
                        </Stack>
                    </Card>

                    {account.users && account.users.length > 0 && (
                        <Card className={classes.accountCard}>
                            <Stack gap="md">
                                <Text size="lg" fw={600}>{t`Users`}</Text>
                                <div className={classes.usersList}>
                                    {account.users.map((user) => (
                                        <div key={user.id} className={classes.userItem}>
                                            <div>
                                                <Text size="sm" fw={500}>
                                                    {user.first_name} {user.last_name}
                                                </Text>
                                                <Text size="xs" c="dimmed">{user.email}</Text>
                                            </div>
                                            <Badge size="sm">{user.role}</Badge>
                                        </div>
                                    ))}
                                </div>
                            </Stack>
                        </Card>
                    )}
                </Stack>
            </Container>

            {showVatModal && (
                <EditAccountVatSettingsModal
                    accountId={accountId!}
                    vatSetting={account.vat_setting}
                    onClose={() => setShowVatModal(false)}
                />
            )}
        </>
    );
};

export default AccountDetail;
