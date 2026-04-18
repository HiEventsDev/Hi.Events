import {useForm} from "@mantine/form";
import {ContactAttributeDefinition, GenericModalProps} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, Group, Select, Switch, TextInput} from "@mantine/core";
import {useUpdateContactAttributeDefinition} from "../../../mutations/useUpdateContactAttributeDefinition.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SortableOptionsInput} from "../../common/SortableOptionsInput";

interface EditContactAttributeDefinitionModalProps extends GenericModalProps {
    definition: ContactAttributeDefinition;
}

export const EditContactAttributeDefinitionModal = ({definition, onClose}: EditContactAttributeDefinitionModalProps) => {
    const updateMutation = useUpdateContactAttributeDefinition();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<Partial<ContactAttributeDefinition>>({
        initialValues: {
            name: definition.name,
            label: definition.label,
            type: definition.type,
            options: definition.options || [],
            sort_order: definition.sort_order,
            is_active: definition.is_active,
        },
        validate: {
            name: (value) => (!value ? t`Name is required` : null),
            label: (value) => (!value ? t`Label is required` : null),
        },
    });

    const handleSubmit = () => {
        form.validate();
        if (form.isValid()) {
            updateMutation.mutate({
                definitionId: definition.id,
                definitionData: form.values,
            }, {
                onSuccess: () => {
                    showSuccess(t`Attribute definition updated successfully`);
                    onClose();
                },
                onError: (error) => formErrorHandler(form, error),
            });
        }
    };

    const showOptions = form.values.type === 'select' || form.values.type === 'multi_select';

    return (
        <Modal heading={t`Edit Attribute Definition`} onClose={onClose} opened>
            <TextInput
                label={t`Label`}
                placeholder={t`e.g., Role`}
                required
                {...form.getInputProps('label')}
                mb="sm"
            />
            <TextInput
                label={t`Name`}
                placeholder={t`e.g., role`}
                required
                {...form.getInputProps('name')}
                mb="sm"
            />
            <Select
                label={t`Type`}
                data={[
                    {value: 'text', label: t`Text`},
                    {value: 'select', label: t`Single Select`},
                    {value: 'multi_select', label: t`Multi Select`},
                ]}
                {...form.getInputProps('type')}
                mb="sm"
            />
            {showOptions && (
                <div style={{marginBottom: 'var(--mantine-spacing-sm)'}}>
                    <SortableOptionsInput
                        label={t`Options`}
                        value={form.values.options ?? []}
                        onChange={(value) => form.setFieldValue('options', value)}
                    />
                </div>
            )}
            <Switch
                label={t`Active`}
                checked={form.values.is_active}
                onChange={(event) => form.setFieldValue('is_active', event.currentTarget.checked)}
                mb="md"
            />
            <Group justify="flex-end" mt="xl" mb="md">
                <Button variant="default" onClick={onClose} disabled={updateMutation.isPending}>
                    {t`Cancel`}
                </Button>
                <Button loading={updateMutation.isPending} onClick={handleSubmit}>
                    {updateMutation.isPending ? t`Working...` : t`Update Attribute`}
                </Button>
            </Group>
        </Modal>
    );
};
