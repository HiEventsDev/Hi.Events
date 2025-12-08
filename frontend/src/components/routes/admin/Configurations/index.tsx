import {Container, Title, Stack, Card, Text, Group, Button, Badge, ActionIcon, Alert, NumberInput, TextInput, Skeleton} from "@mantine/core";
import {t} from "@lingui/macro";
import {useGetAllConfigurations} from "../../../../queries/useGetAllConfigurations";
import {useCreateConfiguration} from "../../../../mutations/useCreateConfiguration";
import {useUpdateConfiguration} from "../../../../mutations/useUpdateConfiguration";
import {useDeleteConfiguration} from "../../../../mutations/useDeleteConfiguration";
import {IconPlus, IconEdit, IconTrash, IconAlertTriangle} from "@tabler/icons-react";
import {useState} from "react";
import {Modal} from "../../../common/Modal";
import {useForm} from "@mantine/form";
import {showSuccess, showError} from "../../../../utilites/notifications";
import {AccountConfiguration} from "../../../../api/admin.client";
import classes from "./Configurations.module.scss";

interface ConfigurationFormValues {
    name: string;
    fixed_fee: number;
    percentage_fee: number;
}

const Configurations = () => {
    const {data: configurationsData, isLoading} = useGetAllConfigurations();
    const deleteMutation = useDeleteConfiguration();
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingConfig, setEditingConfig] = useState<AccountConfiguration | null>(null);

    const configurations = configurationsData?.data || [];

    const handleDelete = (config: AccountConfiguration) => {
        if (config.is_system_default) {
            showError(t`Cannot delete the system default configuration`);
            return;
        }

        if (window.confirm(t`Are you sure you want to delete this configuration? This may affect accounts using it.`)) {
            deleteMutation.mutate(config.id, {
                onSuccess: () => showSuccess(t`Configuration deleted successfully`),
                onError: () => showError(t`Failed to delete configuration`),
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
                        <Title order={1}>{t`Configurations`}</Title>
                        <Button
                            leftSection={<IconPlus size={16} />}
                            onClick={() => setShowCreateModal(true)}
                        >
                            {t`Create Configuration`}
                        </Button>
                    </Group>

                    <Alert icon={<IconAlertTriangle size={16} />} color="yellow">
                        {t`Configuration names are visible to end users. The "Fixed Fee" and "Percentage Fee" are application fees charged in USD on all transactions.`}
                    </Alert>

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
                                        </Group>
                                        <Group gap="xl">
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Fixed Fee (USD)`}</Text>
                                                <Text size="sm" fw={500}>${config.application_fees?.fixed || 0}</Text>
                                            </div>
                                            <div>
                                                <Text size="xs" c="dimmed">{t`Percentage Fee`}</Text>
                                                <Text size="sm" fw={500}>{config.application_fees?.percentage || 0}%</Text>
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
                    onClose={() => setShowCreateModal(false)}
                />
            )}

            {editingConfig && (
                <ConfigurationModal
                    configuration={editingConfig}
                    onClose={() => setEditingConfig(null)}
                />
            )}
        </>
    );
};

interface ConfigurationModalProps {
    configuration?: AccountConfiguration;
    onClose: () => void;
}

const ConfigurationModal = ({configuration, onClose}: ConfigurationModalProps) => {
    const createMutation = useCreateConfiguration();
    const updateMutation = useUpdateConfiguration(configuration?.id || 0);
    const isEditing = !!configuration;

    const form = useForm<ConfigurationFormValues>({
        initialValues: {
            name: configuration?.name || '',
            fixed_fee: configuration?.application_fees?.fixed || 0,
            percentage_fee: configuration?.application_fees?.percentage || 0,
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
            },
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
            heading={isEditing ? t`Edit Configuration` : t`Create Configuration`}
            onClose={onClose}
            opened
        >
            {isEditing && configuration?.is_system_default && (
                <Alert icon={<IconAlertTriangle size={16} />} color="orange" mb="md">
                    {t`Warning: This is the system default configuration. Changes will affect all accounts that don't have a specific configuration assigned.`}
                </Alert>
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

                    <NumberInput
                        label={t`Fixed Fee (USD)`}
                        description={t`Fixed fee charged per transaction in USD`}
                        placeholder="0.00"
                        decimalScale={2}
                        fixedDecimalScale
                        min={0}
                        prefix="$"
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

                    <Button
                        fullWidth
                        loading={createMutation.isPending || updateMutation.isPending}
                        type="submit"
                    >
                        {isEditing ? t`Save Changes` : t`Create Configuration`}
                    </Button>
                </Stack>
            </form>
        </Modal>
    );
};

export default Configurations;
