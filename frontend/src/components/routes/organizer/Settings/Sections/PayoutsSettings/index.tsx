import {
    Accordion,
    ActionIcon,
    Anchor,
    Badge,
    Box,
    Button,
    CopyButton,
    Divider,
    Group,
    Loader,
    Select,
    Stack,
    Text,
    ThemeIcon,
    Title,
    Tooltip,
} from "@mantine/core";
import {
    IconAlertCircle,
    IconBolt,
    IconBrandStripe,
    IconCheck,
    IconCircleCheck,
    IconCopy,
    IconExternalLink,
    IconShieldCheck,
    IconWorld,
} from "@tabler/icons-react";
import {Callout} from "../../../../../common/Callout";
import {t, Trans} from "@lingui/macro";
import {useEffect, useState} from "react";
import {useParams} from "react-router";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {useGetOrganizerStripeConnect} from "../../../../../../queries/useGetOrganizerStripeConnect";
import {useCreateOrGetOrganizerStripeConnect} from "../../../../../../queries/useCreateOrGetOrganizerStripeConnect";
import {useCopyStripeConnectFromOrganizer} from "../../../../../../mutations/useCopyStripeConnectFromOrganizer";
import {OrganizerStripeConnectAccount, ReusableStripeConnection} from "../../../../../../types";
import {showError, showSuccess} from "../../../../../../utilites/notifications";
import {CapabilityList} from "../../../Payments/CapabilityList";
import {RequirementsList} from "../../../Payments/RequirementsList";
import {VatSettings as VatSettingsBody} from "../../../Payments/Vat/VatSettings";
import {getVatInfo} from "../../../Payments/Vat/VatNotice";
import classes from "../../../Payments/Payments.module.scss";

const summarizeActiveCapabilities = (capabilities: Record<string, string>): number => {
    return Object.values(capabilities ?? {}).filter((status) => status === "active").length;
};

export const PayoutsSettings = () => {
    const {organizerId} = useParams();
    const [pendingConnect, setPendingConnect] = useState(false);

    const stripeQuery = useGetOrganizerStripeConnect(organizerId, {enabled: !!organizerId});
    const connectDetails = useCreateOrGetOrganizerStripeConnect(
        organizerId,
        pendingConnect && !!organizerId,
    );
    const copyMutation = useCopyStripeConnectFromOrganizer();

    const data = stripeQuery.data;
    const primaryAccount: OrganizerStripeConnectAccount | undefined =
        (data?.primary_stripe_account_id
            ? data?.stripe_connect_accounts?.find(a => a.stripe_account_id === data.primary_stripe_account_id)
            : undefined)
        ?? data?.stripe_connect_accounts?.[0];
    const hasConnected = !!data?.has_completed_setup;
    const hasInProgress = !!primaryAccount && !primaryAccount.is_setup_complete;
    const reusable: ReusableStripeConnection[] = data?.reusable_connections ?? [];
    const [selectedReusable, setSelectedReusable] = useState<string | null>(null);
    const [reuseExpanded, setReuseExpanded] = useState(false);
    const [requirementsExpanded, setRequirementsExpanded] = useState(false);

    useEffect(() => {
        if (!pendingConnect || connectDetails.isLoading) return;
        if (!connectDetails.data) return;

        const url = connectDetails.data.connect_url;
        if (!url) {
            showError(t`Stripe didn't return a setup link. Please try again.`);
            setPendingConnect(false);
            return;
        }
        showSuccess(t`Redirecting to Stripe...`);
        if (typeof window !== "undefined") {
            window.location.href = String(url);
        }
    }, [pendingConnect, connectDetails.isLoading, connectDetails.data]);

    useEffect(() => {
        if (connectDetails.isError) {
            setPendingConnect(false);
            showError(t`We couldn't reach Stripe just now. Please try again in a moment.`);
        }
    }, [connectDetails.isError]);

    const handleConnect = () => {
        setPendingConnect(true);
    };

    const handleReuse = () => {
        if (!selectedReusable || !organizerId) return;
        const source = reusable.find((r) => String(r.stripe_account_id) === selectedReusable);
        if (!source) return;
        copyMutation.mutate(
            {organizerId, sourceOrganizerId: source.organizer_id},
            {
                onSuccess: () => {
                    showSuccess(t`Stripe connection copied from ${source.organizer_name}.`);
                    setSelectedReusable(null);
                },
                onError: () => {
                    showError(t`We couldn't copy that Stripe connection. Please try again.`);
                },
            },
        );
    };

    const renderInlineReuse = () => {
        if (reusable.length === 0) return null;

        const options = reusable.map((r) => ({
            value: String(r.stripe_account_id),
            label: r.country ? `${r.organizer_name} (${r.country})` : r.organizer_name,
        }));

        return (
            <Box mt="md">
                <Divider my="sm"/>
                {!reuseExpanded ? (
                    <Anchor
                        component="button"
                        type="button"
                        size="xs"
                        c="dimmed"
                        underline="hover"
                        onClick={() => setReuseExpanded(true)}
                    >
                        {t`Already use Stripe on another organizer? Reuse that connection.`}
                    </Anchor>
                ) : (
                    <Stack gap="xs">
                        <Text size="xs" c="dimmed">
                            {t`Reuse a Stripe connection from another organizer in this account.`}
                        </Text>
                        <Group gap="xs" align="flex-start" wrap="nowrap" className={classes.reuseInlineRow}>
                            <Select
                                placeholder={t`Choose an organizer`}
                                data={options}
                                value={selectedReusable}
                                onChange={setSelectedReusable}
                                searchable
                                nothingFoundMessage={t`No connections available`}
                                size="xs"
                                style={{flex: 1}}
                            />
                            <Button
                                variant="light"
                                size="xs"
                                disabled={!selectedReusable}
                                loading={copyMutation.isPending}
                                onClick={handleReuse}
                            >
                                {t`Reuse`}
                            </Button>
                        </Group>
                    </Stack>
                )}
            </Box>
        );
    };

    const renderHero = () => (
        <>
            <Group gap="md" mb="sm" wrap="nowrap">
                <ThemeIcon size={48} radius="md" variant="light" color="indigo">
                    <IconBrandStripe size={28}/>
                </ThemeIcon>
                <Box>
                    <Title order={4}>{t`Get paid with Stripe`}</Title>
                    <Text c="dimmed" size="sm" mt={4}>
                        {t`We partner with Stripe to send payouts straight to your bank account.`}
                    </Text>
                </Box>
            </Group>

            <Text size="sm" mb="md" lh={1.5}>
                {t`Setup takes just a few minutes — you don't need an existing Stripe account. Stripe handles cards, wallets, regional payment methods, and fraud protection so you can focus on your event.`}
            </Text>

            <Box className={classes.heroBullets}>
                <span className={classes.bullet}>
                    <IconBolt size={16}/>{t`Fast payouts to your bank`}
                </span>
                <span className={classes.bullet}>
                    <IconWorld size={16}/>{t`135+ currencies & 40+ payment methods`}
                </span>
                <span className={classes.bullet}>
                    <IconShieldCheck size={16}/>{t`Built-in fraud protection`}
                </span>
            </Box>

            <Button
                size="md"
                leftSection={<IconBrandStripe size={18}/>}
                onClick={handleConnect}
                loading={pendingConnect && (connectDetails.isLoading || !connectDetails.data)}
            >
                {t`Connect with Stripe`}
            </Button>

            {renderInlineReuse()}
        </>
    );

    const renderInProgress = (account: OrganizerStripeConnectAccount) => {
        const hasRequirements = account.requirements.past_due.length > 0
            || account.requirements.currently_due.length > 0;

        return (
            <>
                <Group gap="md" mb="md" wrap="nowrap">
                    <ThemeIcon size={40} radius="md" variant="light" color="orange">
                        <IconAlertCircle size={22}/>
                    </ThemeIcon>
                    <div>
                        <Title order={4}>{t`Finish setting up Stripe`}</Title>
                        <Text c="dimmed" size="sm">
                            {t`Stripe will walk you through a few quick questions to finish setup.`}
                        </Text>
                    </div>
                </Group>

                <Button
                    leftSection={<IconExternalLink size={16}/>}
                    onClick={handleConnect}
                    loading={pendingConnect && (connectDetails.isLoading || !connectDetails.data)}
                >
                    {t`Complete Stripe setup`}
                </Button>

                {renderInlineReuse()}

                {hasRequirements && (
                    <Box mt="md">
                        <Anchor
                            component="button"
                            type="button"
                            size="xs"
                            c="dimmed"
                            underline="hover"
                            onClick={() => setRequirementsExpanded((v) => !v)}
                        >
                            {requirementsExpanded ? t`Hide details` : t`See what Stripe still needs`}
                        </Anchor>

                        {requirementsExpanded && (
                            <Box mt="sm">
                                {account.requirements.past_due.length > 0 && (
                                    <Box mb="sm">
                                        <Text size="xs" c="dimmed" mb={4}>{t`Past due`}</Text>
                                        <RequirementsList items={account.requirements.past_due}/>
                                    </Box>
                                )}
                                {account.requirements.currently_due.length > 0 && (
                                    <Box>
                                        <Text size="xs" c="dimmed" mb={4}>{t`Still needed`}</Text>
                                        <RequirementsList items={account.requirements.currently_due}/>
                                    </Box>
                                )}
                            </Box>
                        )}
                    </Box>
                )}
            </>
        );
    };

    const renderConnected = (account: OrganizerStripeConnectAccount) => {
        const activeCount = summarizeActiveCapabilities(account.capabilities);
        const stripeAccountId = account.stripe_account_id ?? "";
        const stripeDashboardUrl = stripeAccountId
            ? `https://dashboard.stripe.com/${stripeAccountId}`
            : `https://dashboard.stripe.com/`;

        return (
            <>
                <Box className={classes.connectedHeader} mb="md">
                    <Box className={classes.connectedIdentity}>
                        <ThemeIcon size={48} radius="md" variant="light" color="green">
                            <IconCircleCheck size={26}/>
                        </ThemeIcon>
                        <div>
                            <Title order={4}>{t`Stripe connected`}</Title>
                            <Text c="dimmed" size="sm" className={classes.connectedMeta}>
                                {account.country
                                    ? t`Account · ${account.country} · ${account.account_type ?? "Standard"}`
                                    : t`Account · ${account.account_type ?? "Standard"}`}
                            </Text>
                            {stripeAccountId && (
                                <Group gap={4} mt={2} wrap="nowrap" className={classes.accountIdRow}>
                                    <Text size="xs" c="dimmed" className={classes.accountIdValue}>
                                        {stripeAccountId}
                                    </Text>
                                    <CopyButton value={stripeAccountId} timeout={1500}>
                                        {({copied, copy}) => (
                                            <Tooltip
                                                label={copied ? t`Copied` : t`Copy account ID`}
                                                withArrow
                                                position="right"
                                            >
                                                <ActionIcon
                                                    size="xs"
                                                    variant="subtle"
                                                    color={copied ? "green" : "gray"}
                                                    onClick={copy}
                                                    aria-label={t`Copy account ID`}
                                                >
                                                    {copied ? <IconCheck size={12}/> : <IconCopy size={12}/>}
                                                </ActionIcon>
                                            </Tooltip>
                                        )}
                                    </CopyButton>
                                </Group>
                            )}
                        </div>
                    </Box>
                    <Button
                        component="a"
                        href={stripeDashboardUrl}
                        target="_blank"
                        rel="noreferrer"
                        variant="subtle"
                        leftSection={<IconExternalLink size={16}/>}
                        className={classes.manageButton}
                    >
                        {t`Manage on Stripe`}
                    </Button>
                </Box>

                <Box className={classes.statusGrid}>
                    <div className={classes.statusCell}>
                        <span className={classes.statusCellLabel}>{t`Charges`}</span>
                        <span className={classes.statusCellValue}>
                            {account.charges_enabled
                                ? <Badge color="green" leftSection={<IconCheck size={12}/>}>{t`Enabled`}</Badge>
                                : <Badge color="red">{t`Disabled`}</Badge>}
                        </span>
                    </div>
                    <div className={classes.statusCell}>
                        <span className={classes.statusCellLabel}>{t`Payouts`}</span>
                        <span className={classes.statusCellValue}>
                            {account.payouts_enabled
                                ? <Badge color="green" leftSection={<IconCheck size={12}/>}>{t`Enabled`}</Badge>
                                : <Badge color="red">{t`Disabled`}</Badge>}
                        </span>
                    </div>
                    <div className={classes.statusCell}>
                        <span className={classes.statusCellLabel}>{t`Active payment methods`}</span>
                        <span className={classes.statusCellValue}>
                            <Trans>{activeCount} enabled</Trans>
                        </span>
                    </div>
                </Box>

                <Accordion variant="separated" radius="md" mt="md">
                    <Accordion.Item value="capabilities">
                        <Accordion.Control>{t`View all capabilities`}</Accordion.Control>
                        <Accordion.Panel>
                            <CapabilityList capabilities={account.capabilities}/>
                        </Accordion.Panel>
                    </Accordion.Item>
                </Accordion>

                {account.requirements.eventually_due.length > 0 && (
                    <Callout
                        variant="tip"
                        title={t`Stripe will need a few more details soon`}
                        style={{marginTop: 16}}
                    >
                        <Text size="sm" mb="xs">
                            {t`Provide the following before Stripe's next review to keep payouts flowing.`}
                        </Text>
                        <RequirementsList items={account.requirements.eventually_due} severity="eventually"/>
                    </Callout>
                )}

                {organizerId && getVatInfo(account.country).isEU && (
                    <Box mt="xl">
                        <Divider mb="md"/>
                        <Title order={4} mb={4}>{t`VAT`}</Title>
                        <Text c="dimmed" size="sm" mb="md">
                            {t`How VAT is applied to the platform fees we charge you.`}
                        </Text>
                        <VatSettingsBody organizerId={organizerId} stripeCountry={account.country}/>
                    </Box>
                )}
            </>
        );
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Payouts`}
                description={t`Connect Stripe to receive ticket payments directly to your bank account.`}
            />
            {stripeQuery.isLoading ? (
                <Group justify="center" p="xl">
                    <Loader/>
                </Group>
            ) : hasConnected && primaryAccount ? (
                renderConnected(primaryAccount)
            ) : hasInProgress && primaryAccount ? (
                renderInProgress(primaryAccount)
            ) : (
                renderHero()
            )}
        </Card>
    );
};
