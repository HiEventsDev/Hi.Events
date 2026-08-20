import {Container, Title, Stack, Card, Text, Group, Button, Badge, Skeleton, Select, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {useParams, useNavigate} from "react-router";
import {useState} from "react";
import {useGetAdminAccount} from "../../../../../queries/useGetAdminAccount";
import {useGetMessagingTiers} from "../../../../../queries/useGetMessagingTiers";
import {useUpdateAccountMessagingTier} from "../../../../../mutations/useUpdateAccountMessagingTier";
import {useUpdateAccountVerification} from "../../../../../mutations/useUpdateAccountVerification";
import {IconArrowLeft, IconCalendar, IconWorld, IconBuildingBank, IconUsers} from "@tabler/icons-react";
import {showSuccess, showError} from "../../../../../utilites/notifications";
import {AdminOrganizerSummary} from "../../../../../api/admin.client";
import {OrganizerAdminModal} from "./OrganizerAdminModal";
import classes from "./AccountDetail.module.scss";

const AccountDetail = () => {
    const {accountId} = useParams();
    const navigate = useNavigate();
    const {data: accountData, isLoading} = useGetAdminAccount(accountId);
    const {data: messagingTiersData} = useGetMessagingTiers();
    const updateTierMutation = useUpdateAccountMessagingTier(accountId!);
    const updateVerificationMutation = useUpdateAccountVerification(accountId!);
    const [managedOrganizer, setManagedOrganizer] = useState<AdminOrganizerSummary | null>(null);

    const account = accountData?.data;
    const messagingTiers = messagingTiersData?.data || [];
    const organizers = account?.organizers ?? [];

    const formatDate = (dateString?: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    };

    const handleMessagingTierChange = (value: string | null) => {
        if (!value) return;

        updateTierMutation.mutate(parseInt(value, 10), {
            onSuccess: () => showSuccess(t`Messaging tier updated successfully`),
            onError: () => showError(t`Failed to update messaging tier`),
        });
    };

    const handleVerificationChange = (isVerified: boolean) => {
        updateVerificationMutation.mutate(isVerified, {
            onSuccess: () => showSuccess(
                isVerified
                    ? t`Account marked as verified`
                    : t`Account verification revoked`
            ),
            onError: () => showError(t`Failed to update account verification`),
        });
    };

    const getTierBadgeColor = (tierName?: string) => {
        if (!tierName) return 'gray';
        const name = tierName.toLowerCase();
        if (name.includes('premium')) return 'green';
        if (name.includes('trusted')) return 'blue';
        return 'gray';
    };

    const getSelectedTierDetails = () => {
        const selectedTierId = account?.messaging_tier?.id;
        if (!selectedTierId) return null;
        return messagingTiers.find(tier => tier.id === selectedTierId);
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

    const tierOptions = messagingTiers.map((tier) => ({
        value: String(tier.id),
        label: tier.name,
    }));

    const selectedTierDetails = getSelectedTierDetails();

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
                            <Group justify="space-between">
                                <Text size="lg" fw={600}>{t`Verification`}</Text>
                                <Badge color={account.is_manually_verified ? 'green' : 'orange'}>
                                    {account.is_manually_verified ? t`Verified` : t`Unverified`}
                                </Badge>
                            </Group>

                            <Switch
                                label={t`Manually verified`}
                                description={t`Verified accounts can send messages to attendees and manage email templates. Connecting Stripe verifies an account automatically.`}
                                checked={account.is_manually_verified}
                                onChange={(event) => handleVerificationChange(event.currentTarget.checked)}
                                disabled={updateVerificationMutation.isPending}
                                data-testid="account-verification-switch"
                            />
                        </Stack>
                    </Card>

                    <Card className={classes.accountCard}>
                        <Stack gap="md">
                            <Group justify="space-between">
                                <Text size="lg" fw={600}>{t`Messaging Tier`}</Text>
                                {account.messaging_tier && (
                                    <Badge color={getTierBadgeColor(account.messaging_tier.name)}>
                                        {account.messaging_tier.name}
                                    </Badge>
                                )}
                            </Group>

                            <Select
                                label={t`Assigned Tier`}
                                description={t`Select the messaging tier for this account. This controls message limits and link permissions.`}
                                data={tierOptions}
                                value={account.messaging_tier?.id ? String(account.messaging_tier.id) : null}
                                onChange={handleMessagingTierChange}
                                placeholder={t`Select a tier`}
                                disabled={updateTierMutation.isPending}
                            />

                            {selectedTierDetails && (
                                <div className={classes.infoGrid}>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Max Messages / 24h`}</Text>
                                        <Text size="sm" fw={500}>
                                            {selectedTierDetails.max_messages_per_24h}
                                        </Text>
                                    </div>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Max Recipients / Message`}</Text>
                                        <Text size="sm" fw={500}>
                                            {selectedTierDetails.max_recipients_per_message}
                                        </Text>
                                    </div>
                                    <div className={classes.infoItem}>
                                        <Text size="xs" c="dimmed">{t`Links Allowed`}</Text>
                                        <Badge color={selectedTierDetails.links_allowed ? 'green' : 'gray'}>
                                            {selectedTierDetails.links_allowed ? t`Yes` : t`No`}
                                        </Badge>
                                    </div>
                                </div>
                            )}
                        </Stack>
                    </Card>

                    {organizers.length > 0 && (
                        <Card className={classes.accountCard}>
                            <Stack gap="md">
                                <Text size="lg" fw={600}>{t`Organizers`}</Text>
                                <Stack gap="xs">
                                    {organizers.map((organizer) => {
                                        const fees = organizer.configuration?.application_fees;
                                        const vat = organizer.vat_setting;
                                        return (
                                            <Group
                                                key={String(organizer.id)}
                                                justify="space-between"
                                                wrap="nowrap"
                                                className={classes.userItem}
                                            >
                                                <Stack gap={2}>
                                                    <Text size="sm" fw={500}>{organizer.name}</Text>
                                                    <Group gap="xs">
                                                        {organizer.configuration && (
                                                            <Badge size="xs" variant="light">
                                                                {organizer.configuration.name}
                                                                {fees ? ` · ${fees.percentage}% + ${fees.fixed} ${fees.currency}` : ""}
                                                            </Badge>
                                                        )}
                                                        {vat?.vat_registered ? (
                                                            <Badge size="xs" color="green" variant="light">
                                                                {t`VAT: ${vat.vat_number ?? "—"}`}
                                                                {vat.vat_validated ? ` ✓` : ""}
                                                            </Badge>
                                                        ) : (
                                                            <Badge size="xs" color="gray" variant="light">
                                                                {t`VAT: not registered`}
                                                            </Badge>
                                                        )}
                                                    </Group>
                                                </Stack>
                                                <Button
                                                    variant="subtle"
                                                    size="xs"
                                                    onClick={() => setManagedOrganizer(organizer)}
                                                >
                                                    {t`Manage`}
                                                </Button>
                                            </Group>
                                        );
                                    })}
                                </Stack>
                            </Stack>
                        </Card>
                    )}

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

            {managedOrganizer && (
                <OrganizerAdminModal
                    organizer={managedOrganizer}
                    onClose={() => setManagedOrganizer(null)}
                />
            )}
        </>
    );
};

export default AccountDetail;
