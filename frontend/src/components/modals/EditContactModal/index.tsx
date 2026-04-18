import {useEffect, useState} from "react";
import {useForm} from "@mantine/form";
import {Contact, GenericModalProps} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, Divider, Group, MultiSelect, Select, Tabs, TextInput} from "@mantine/core";
import {IconForms, IconHistory} from "@tabler/icons-react";
import {useUpdateContact} from "../../../mutations/useUpdateContact.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useGetContactAttributeDefinitions} from "../../../queries/useGetContactAttributeDefinitions.ts";
import {AttributeHistoryPanel} from "./AttributeHistoryPanel";
import classes from "./EditContactModal.module.scss";

interface EditContactModalProps extends GenericModalProps {
    contact: Contact;
}

export const EditContactModal = ({contact, onClose}: EditContactModalProps) => {
    const updateMutation = useUpdateContact();
    const formErrorHandler = useFormErrorResponseHandler();
    const {data: definitionsData} = useGetContactAttributeDefinitions();
    const activeDefinitions = definitionsData?.data?.filter(d => d.is_active) ?? [];
    const historyCount = contact.attributes_history?.length ?? 0;
    const [activeTab, setActiveTab] = useState<string | null>('details');

    const form = useForm<{
        first_name: string;
        last_name: string;
        attributes: Record<string, string | string[]>;
    }>({
        initialValues: {
            first_name: contact.first_name || '',
            last_name: contact.last_name || '',
            attributes: {},
        },
    });

    useEffect(() => {
        if (!activeDefinitions.length) return;
        const attrs: Record<string, string | string[]> = {};
        activeDefinitions.forEach(def => {
            const existing = contact.attributes?.[def.name];
            attrs[def.name] = def.type === 'multi_select'
                ? Array.isArray(existing) ? (existing as string[]) : []
                : (typeof existing === 'string' ? existing : '');
        });
        form.setFieldValue('attributes', attrs);
    }, [definitionsData]);

    const handleSubmit = () => {
        form.validate();
        if (form.isValid()) {
            updateMutation.mutate({
                contactId: contact.id,
                contactData: form.values,
            }, {
                onSuccess: () => {
                    showSuccess(t`Contact updated successfully`);
                    onClose();
                },
                onError: (error) => formErrorHandler(form, error),
            });
        }
    };

    return (
        <Modal heading={t`Edit Contact`} onClose={onClose} opened>
            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List mb="md">
                    <Tabs.Tab value="details" leftSection={<IconForms size={14}/>}>
                        {t`Details`}
                    </Tabs.Tab>
                    <Tabs.Tab value="history" leftSection={<IconHistory size={14}/>}>
                        {historyCount > 0 ? t`History (${historyCount})` : t`History`}
                    </Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="details">
                    <TextInput
                        label={t`Email`}
                        value={contact.email}
                        disabled
                        mb="sm"
                    />
                    <TextInput
                        label={t`First Name`}
                        placeholder={t`First Name`}
                        {...form.getInputProps('first_name')}
                        mb="sm"
                    />
                    <TextInput
                        label={t`Last Name`}
                        placeholder={t`Last Name`}
                        {...form.getInputProps('last_name')}
                        mb="md"
                    />
                    {activeDefinitions.length > 0 && (
                        <>
                            <Divider label={t`Attributes`} labelPosition="left" mb="sm"/>
                            <div className={classes.attributesGrid}>
                                {activeDefinitions.map(def => {
                                    const fieldPath = `attributes.${def.name}`;
                                    if (def.type === 'select') {
                                        return (
                                            <Select
                                                key={def.name}
                                                label={def.label}
                                                data={def.options ?? []}
                                                clearable
                                                {...form.getInputProps(fieldPath)}
                                            />
                                        );
                                    }
                                    if (def.type === 'multi_select') {
                                        return (
                                            <MultiSelect
                                                key={def.name}
                                                label={def.label}
                                                data={def.options ?? []}
                                                {...form.getInputProps(fieldPath)}
                                            />
                                        );
                                    }
                                    return (
                                        <TextInput
                                            key={def.name}
                                            label={def.label}
                                            {...form.getInputProps(fieldPath)}
                                        />
                                    );
                                })}
                            </div>
                        </>
                    )}
                    <Group justify="flex-end" mt="xl" mb="md">
                        <Button variant="default" onClick={onClose} disabled={updateMutation.isPending}>
                            {t`Cancel`}
                        </Button>
                        <Button loading={updateMutation.isPending} onClick={handleSubmit}>
                            {updateMutation.isPending ? t`Working...` : t`Update Contact`}
                        </Button>
                    </Group>
                </Tabs.Panel>

                <Tabs.Panel value="history">
                    <AttributeHistoryPanel contact={contact}/>
                    <Group justify="flex-end" mt="xl" mb="md">
                        <Button variant="default" onClick={onClose}>
                            {t`Close`}
                        </Button>
                    </Group>
                </Tabs.Panel>
            </Tabs>
        </Modal>
    );
};
