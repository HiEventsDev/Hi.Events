import {Container, Title, Stack, Card, Text, Group, Button, Badge, ActionIcon, NumberInput, TextInput, Skeleton, Switch, Select, Checkbox} from "@mantine/core";
import {t} from "@lingui/macro";
import {useGetAllConfigurations} from "../../../../queries/useGetAllConfigurations";
import {useCreateConfiguration} from "../../../../mutations/useCreateConfiguration";
import {useUpdateConfiguration} from "../../../../mutations/useUpdateConfiguration";
import {useDeleteConfiguration} from "../../../../mutations/useDeleteConfiguration";
import {IconPlus, IconEdit, IconTrash} from "@tabler/icons-react";
import {Callout} from "../../../common/Callout";
import {useState} from "react";
import {Modal} from "../../../common/Modal";
import {useForm} from "@mantine/form";
import {showSuccess, showError} from "../../../../utilites/notifications";
import {AccountConfiguration} from "../../../../api/admin.client";
import {currenciesMap} from "../../../../../data/currencies";
import {getCurrencySymbol} from "../../../../utilites/currency";
import classes from "./Configurations.module.scss";

interface ConfigurationFormValues {
    name: string;
    fixed_fee: number;
    percentage_fee: number;
    currency: string;
    branding_removal: boolean;
    upgrades_to_id: string | null;
    bypass_application_fees: boolean;
}

const Configurations = () => {
    const {data: configurationsData, isLoading} = useGetAllConfigurations();
    const deleteMutation = useDeleteConfiguration();
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingConfig, setEditingConfig] = useState<AccountConfiguration | null>(null);

    const configurations = configurationsData?.data || [];
    const upgradeTargetIds = new Set(
        configurations.map((config) => config.upgrades_to_id).filter(Boolean)
    );

    const handleDelete = (config: AccountConfiguration) => {
        if (config.is_system_default) {
            showError(t`Cannot delete the system default configuration`);
            return;
        }

        if (upgradeTargetIds.has(config.id)) {
            showError(t`This plan is an upgrade target for another plan. Remove that link first.`);
            return;
        }

        if (window.confirm(t`Are you sure you want to delete this configuration? This may affect accounts using it.`)) {
            deleteMutation.mutate(config.id, {
                onSuccess: () => showSuccess(t`Configuration deleted successfully`),
                onError: (error: any) => showError(error?.response?.data?.message ?? t`Failed to delete configuration`),
            });
        }
    };

    if (isLoading) {
        return (
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Skeleton height={40} width={200} />
                    <Skeleton height={150} radius="md" />
                    <Skeleton height={150} radius="md" />
                </Stack>
            </Container>
        );
    }

    return (
        <>
            <Container size="xl" p="xl">
                <Stack gap="lg">
                    <Group justify="space-between">
                        <Title order={1}>{t`Plans`}</Title>
                        <Button
                            leftSection={<IconPlus size={16} />}
                            onClick={() => setShowCreateModal(true)}
                        >
                            {t`Create Plan`}
                        </Button>
                    </Group>

                    <Callout variant="tip">
                        {t`Plan names are visible to end users. Fixed fees will be converted to the order currency at the current exchange rate.`}
                    </Callout>

                    <Stack gap="md">
                        {configurations.map((config) => (
                            <Card key={config.id} className={classes.configCard}>
                                <Group justify="space-between" align="flex-start">
                                    <Stack gap="xs">
                                        <Group gap="sm">
                                            <Text fw={600}>{config.name}</Text>
                                            {config.is_system_default && (
                                                <Badge color="blue" size="sm">{t`System Default`}</Badge>
                                            )}
                                            {upgradeTargetIds.has(config.id) && (
                                                <Badge color="grape" size="sm">{t`Upgrade target`}</Badge>
                                            )}
                                            {config.bypass_application_fees && (
                                                <Badge color="orange" size="sm">{t`Fees Bypassed`}</Badge>
                                            )}
                                        </Group>
                                        <Group gap="xl">
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Fixed Fee`}</Text>
                                                <Text size="sm" fw={500}>
                                                    {getCurrencySymbol(config.application_fees?.currency || 'USD')}
                                                    {config.application_fees?.fixed || 0} {config.application_fees?.currency || 'USD'}
                                                </Text>
                                            </div>
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Percentage Fee`}</Text>
                                                <Text size="sm" fw={500}>{config.application_fees?.percentage || 0}%</Text>
                                            </div>
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Features`}</Text>
                                                <Text size="sm" fw={500}>
                                                    {config.features?.branding_removal ? t`Branding removal` : '—'}
                                                </Text>
                                            </div>
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Upgrades to`}</Text>
                                                <Text size="sm" fw={500}>
                                                    {configurations.find((c) => c.id === config.upgrades_to_id)?.name ?? '—'}
                                                </Text>
                                            </div>
                                        </Group>
                                    </Stack>
                                    <Group gap="xs">
                                        <ActionIcon
                                            variant="light"
                                            onClick={() => setEditingConfig(config)}
                                        >
                                            <IconEdit size={16} />
                                        </ActionIcon>
                                        <ActionIcon
                                            variant="light"
                                            color="red"
                                            onClick={() => handleDelete(config)}
                                            disabled={config.is_system_default}
                                        >
                                            <IconTrash size={16} />
                                        </ActionIcon>
                                    </Group>
                                </Group>
                            </Card>
                        ))}

                        {configurations.length === 0 && (
                            <Text c="dimmed" ta="center">{t`No configurations found`}</Text>
                        )}
                    </Stack>
                </Stack>
            </Container>

            {showCreateModal && (
                <ConfigurationModal
                    configurations={configurations}
                    onClose={() => setShowCreateModal(false)}
                />
            )}

            {editingConfig && (
                <ConfigurationModal
                    configurations={configurations}
                    configuration={editingConfig}
                    onClose={() => setEditingConfig(null)}
                />
            )}
        </>
    );
};

interface ConfigurationModalProps {
    configurations: AccountConfiguration[];
    configuration?: AccountConfiguration;
    onClose: () => void;
}

const ConfigurationModal = ({configurations, configuration, onClose}: ConfigurationModalProps) => {
    const createMutation = useCreateConfiguration();
    const updateMutation = useUpdateConfiguration(configuration?.id || 0);
    const isEditing = !!configuration;

    const upgradeOptions = configurations
        .filter((config) => config.id !== configuration?.id)
        .map((config) => ({value: String(config.id), label: config.name}));

    const form = useForm<ConfigurationFormValues>({
        initialValues: {
            name: configuration?.name || '',
            fixed_fee: configuration?.application_fees?.fixed || 0,
            percentage_fee: configuration?.application_fees?.percentage || 0,
            currency: configuration?.application_fees?.currency || 'USD',
            branding_removal: configuration?.features?.branding_removal || false,
            upgrades_to_id: configuration?.upgrades_to_id ? String(configuration.upgrades_to_id) : null,
            bypass_application_fees: configuration?.bypass_application_fees || false,
        },
        validate: {
            name: (value) => {
                if (!value.trim()) return t`Name is required`;
                if (value.length > 255) return t`Name must be less than 255 characters`;
                return null;
            },
            fixed_fee: (value) => value < 0 ? t`Fixed fee must be 0 or greater` : null,
            percentage_fee: (value) => {
                if (value < 0 || value > 100) return t`Percentage must be between 0 and 100`;
                return null;
            },
        },
    });

    const handleSubmit = (values: ConfigurationFormValues) => {
        const data = {
            name: values.name,
            application_fees: {
                fixed: values.fixed_fee,
                percentage: values.percentage_fee,
                currency: values.currency,
            },
            features: {
                branding_removal: values.branding_removal,
            },
            upgrades_to_id: values.upgrades_to_id ? Number(values.upgrades_to_id) : null,
            bypass_application_fees: values.bypass_application_fees,
        };

        const mutation = isEditing ? updateMutation : createMutation;

        mutation.mutate(data, {
            onSuccess: () => {
                showSuccess(isEditing ? t`Configuration updated successfully` : t`Configuration created successfully`);
                onClose();
            },
            onError: () => {
                showError(isEditing ? t`Failed to update configuration` : t`Failed to create configuration`);
            },
        });
    };

    return (
        <Modal
            heading={isEditing ? t`Edit Plan` : t`Create Plan`}
            onClose={onClose}
            opened
        >
            {isEditing && configuration?.is_system_default && (
                <Callout variant="tip">
                    {t`Warning: This is the system default configuration. Changes will affect all accounts that don't have a specific configuration assigned.`}
                </Callout>
            )}

            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="md">
                    <TextInput
                        label={t`Name`}
                        description={t`This name is visible to end users`}
                        placeholder={t`e.g., Standard, Premium, Enterprise`}
                        required
                        {...form.getInputProps('name')}
                    />

                    <Select
                        label={t`Fee Currency`}
                        description={t`The currency in which the fixed fee is defined. It will be converted to the order currency at checkout.`}
                        data={currenciesMap}
                        searchable
                        {...form.getInputProps('currency')}
                    />

                    <NumberInput
                        label={t`Fixed Fee`}
                        description={t`Fixed fee charged per transaction`}
                        placeholder="0.00"
                        decimalScale={2}
                        fixedDecimalScale
                        min={0}
                        prefix={getCurrencySymbol(form.values.currency)}
                        {...form.getInputProps('fixed_fee')}
                    />

                    <NumberInput
                        label={t`Percentage Fee`}
                        description={t`Percentage of transaction amount`}
                        placeholder="0"
                        decimalScale={2}
                        fixedDecimalScale
                        min={0}
                        max={100}
                        suffix="%"
                        {...form.getInputProps('percentage_fee')}
                    />

                    <div>
                        <Text fw={500} size="sm" mb={4}>{t`Features`}</Text>
                        <Checkbox
                            label={t`Branding removal`}
                            description={t`Organizers on this plan can hide platform branding at no extra fee.`}
                            {...form.getInputProps('branding_removal', {type: 'checkbox'})}
                        />
                    </div>

                    <Select
                        label={t`Upgrades to`}
                        description={t`Organizers on this plan can self-serve upgrade to the selected plan.`}
                        placeholder={t`No upgrade path`}
                        data={upgradeOptions}
                        clearable
                        searchable
                        {...form.getInputProps('upgrades_to_id')}
                    />

                    <Switch
                        label={t`Bypass Application Fees`}
                        description={t`When enabled, no application fees will be charged on Stripe Connect transactions. Use this for countries where application fees are not supported.`}
                        {...form.getInputProps('bypass_application_fees', { type: 'checkbox' })}
                    />

                    <Button
                        fullWidth
                        loading={createMutation.isPending || updateMutation.isPending}
                        type="submit"
                    >
                        {isEditing ? t`Save Changes` : t`Create Plan`}
                    </Button>
                </Stack>
            </form>
        </Modal>
    );
};

export default Configurations;
