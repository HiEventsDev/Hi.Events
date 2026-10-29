import {useEffect, useState} from "react";
import {t} from "@lingui/macro";
import {Modal, Stack, Group, Select, Button, NumberInput, TextInput, Switch, Tabs, Text, Badge, Divider} from "@mantine/core";
import {showError, showSuccess} from "../../../../../utilites/notifications";
import {AdminOrganizerSummary} from "../../../../../api/admin.client";
import {useGetAllConfigurations} from "../../../../../queries/useGetAllConfigurations";
import {useAssignOrganizerConfiguration} from "../../../../../mutations/useAssignOrganizerConfiguration";
import {useUpdateOrganizerConfigurationOverride} from "../../../../../mutations/useUpdateOrganizerConfigurationOverride";
import {useUpdateAdminOrganizerVatSetting} from "../../../../../mutations/useUpdateAdminOrganizerVatSetting";

interface Props {
    organizer: AdminOrganizerSummary;
    onClose: () => void;
}

export const OrganizerAdminModal = ({organizer, onClose}: Props) => {
    const {data: configsData} = useGetAllConfigurations();
    const configurations = configsData?.data ?? [];

    const assignMutation = useAssignOrganizerConfiguration(organizer.id);
    const overrideMutation = useUpdateOrganizerConfigurationOverride(organizer.id);
    const vatMutation = useUpdateAdminOrganizerVatSetting(organizer.id);

    const [selectedConfigId, setSelectedConfigId] = useState<string | null>(
        organizer.configuration ? String(organizer.configuration.id) : null,
    );
    const [overridePercentage, setOverridePercentage] = useState<number>(
        organizer.configuration?.application_fees?.percentage ?? 0,
    );
    const [overrideFixed, setOverrideFixed] = useState<number>(
        organizer.configuration?.application_fees?.fixed ?? 0,
    );

    const [vatRegistered, setVatRegistered] = useState<boolean>(
        !!organizer.vat_setting?.vat_registered,
    );
    const [vatNumber, setVatNumber] = useState<string>(organizer.vat_setting?.vat_number ?? "");
    const [vatValidated, setVatValidated] = useState<boolean>(!!organizer.vat_setting?.vat_validated);
    const [businessName, setBusinessName] = useState<string>(organizer.vat_setting?.business_name ?? "");
    const [businessAddress, setBusinessAddress] = useState<string>(organizer.vat_setting?.business_address ?? "");
    const [vatCountryCode, setVatCountryCode] = useState<string>(organizer.vat_setting?.vat_country_code ?? "");

    useEffect(() => {
        setSelectedConfigId(organizer.configuration ? String(organizer.configuration.id) : null);
        setOverridePercentage(organizer.configuration?.application_fees?.percentage ?? 0);
        setOverrideFixed(organizer.configuration?.application_fees?.fixed ?? 0);
    }, [organizer.configuration]);

    const handleAssign = () => {
        if (!selectedConfigId) return;
        assignMutation.mutate(selectedConfigId, {
            onSuccess: () => showSuccess(t`Configuration assigned`),
            onError: () => showError(t`Failed to assign configuration`),
        });
    };

    const handleOverride = () => {
        const currency = organizer.configuration?.application_fees?.currency ?? "USD";
        overrideMutation.mutate(
            {
                application_fees: {
                    percentage: overridePercentage,
                    fixed: overrideFixed,
                    currency,
                },
            },
            {
                onSuccess: () => showSuccess(t`Fee override saved`),
                onError: () => showError(t`Failed to save override`),
            },
        );
    };

    const handleVatSave = () => {
        vatMutation.mutate(
            {
                vat_registered: vatRegistered,
                vat_number: vatRegistered ? (vatNumber || null) : null,
                vat_validated: vatValidated,
                business_name: businessName || null,
                business_address: businessAddress || null,
                vat_country_code: vatCountryCode ? vatCountryCode.toUpperCase() : null,
            },
            {
                onSuccess: () => showSuccess(t`VAT settings updated`),
                onError: () => showError(t`Failed to update VAT settings`),
            },
        );
    };

    const configOptions = configurations.map((c) => ({
        value: String(c.id),
        label: c.is_system_default
            ? `${c.name} (default)`
            : c.default_for_currency
                ? `${c.name} (${c.default_for_currency} default)`
                : c.name,
    }));

    return (
        <Modal opened onClose={onClose} title={t`Manage ${organizer.name}`} size="lg">
            <Tabs defaultValue="configuration">
                <Tabs.List>
                    <Tabs.Tab value="configuration">{t`Configuration`}</Tabs.Tab>
                    <Tabs.Tab value="vat">{t`VAT`}</Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="configuration" pt="md">
                    <Stack gap="md">
                        <div>
                            <Text size="xs" c="dimmed">{t`Currently assigned`}</Text>
                            <Group gap="xs">
                                <Text size="sm" fw={500}>
                                    {organizer.configuration?.name ?? t`(none)`}
                                </Text>
                                {organizer.configuration?.is_system_default && (
                                    <Badge size="xs">{t`Default`}</Badge>
                                )}
                            </Group>
                        </div>

                        <Select
                            label={t`Assign a different plan`}
                            data={configOptions}
                            value={selectedConfigId}
                            onChange={setSelectedConfigId}
                            placeholder={t`Choose a configuration`}
                        />
                        <Button
                            onClick={handleAssign}
                            loading={assignMutation.isPending}
                            disabled={!selectedConfigId || String(organizer.configuration?.id) === selectedConfigId}
                        >
                            {t`Assign plan`}
                        </Button>

                        <Divider my="sm" label={t`Override fees on this organizer`} labelPosition="center" />

                        <Group grow>
                            <NumberInput
                                label={t`Percentage fee (%)`}
                                value={overridePercentage}
                                onChange={(v) => setOverridePercentage(typeof v === "number" ? v : Number(v) || 0)}
                                min={0}
                                max={100}
                                decimalScale={2}
                            />
                            <NumberInput
                                label={t`Fixed fee`}
                                value={overrideFixed}
                                onChange={(v) => setOverrideFixed(typeof v === "number" ? v : Number(v) || 0)}
                                min={0}
                                decimalScale={2}
                            />
                        </Group>
                        <Text size="xs" c="dimmed">
                            {t`Saving an override creates a dedicated configuration for this organizer if it's currently on the system default.`}
                        </Text>
                        <Button
                            variant="light"
                            onClick={handleOverride}
                            loading={overrideMutation.isPending}
                        >
                            {t`Save fee override`}
                        </Button>
                    </Stack>
                </Tabs.Panel>

                <Tabs.Panel value="vat" pt="md">
                    <Stack gap="md">
                        <Switch
                            label={t`VAT registered`}
                            checked={vatRegistered}
                            onChange={(e) => setVatRegistered(e.currentTarget.checked)}
                        />
                        <TextInput
                            label={t`VAT number`}
                            value={vatNumber}
                            onChange={(e) => setVatNumber(e.currentTarget.value)}
                            disabled={!vatRegistered}
                            placeholder="IE1234567A"
                        />
                        <Switch
                            label={t`Mark as validated (admin override)`}
                            checked={vatValidated}
                            onChange={(e) => setVatValidated(e.currentTarget.checked)}
                        />
                        <TextInput
                            label={t`Business name`}
                            value={businessName}
                            onChange={(e) => setBusinessName(e.currentTarget.value)}
                        />
                        <TextInput
                            label={t`Business address`}
                            value={businessAddress}
                            onChange={(e) => setBusinessAddress(e.currentTarget.value)}
                        />
                        <TextInput
                            label={t`VAT country code`}
                            value={vatCountryCode}
                            onChange={(e) => setVatCountryCode(e.currentTarget.value.toUpperCase().slice(0, 2))}
                            maxLength={2}
                            placeholder="IE"
                        />
                        <Button onClick={handleVatSave} loading={vatMutation.isPending}>
                            {t`Save VAT settings`}
                        </Button>
                    </Stack>
                </Tabs.Panel>
            </Tabs>
        </Modal>
    );
};
