import {useForm} from "@mantine/form";
import {ContactAttributeDefinition, GenericModalProps} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, Group, Select, Switch, TextInput} from "@mantine/core";
import {useCreateContactAttributeDefinition} from "../../../mutations/useCreateContactAttributeDefinition.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SortableOptionsInput} from "../../common/SortableOptionsInput";

export const CreateContactAttributeDefinitionModal = ({onClose}: GenericModalProps) => {
    const createMutation = useCreateContactAttributeDefinition();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<Partial<ContactAttributeDefinition>>({
        initialValues: {
            name: '',
            label: '',
            type: 'text',
            options: [],
            sort_order: 0,
            is_active: true,
        },
        validate: {
            name: (value) => (!value ? t`Name is required` : null),
            label: (value) => (!value ? t`Label is required` : null),
        },
    });

    const handleSubmit = () => {
        form.validate();
        if (form.isValid()) {
            createMutation.mutate({definitionData: form.values}, {
                onSuccess: () => {
                    showSuccess(t`Attribute definition created successfully`);
                    form.reset();
                    onClose();
                },
                onError: (error) => formErrorHandler(form, error),
            });
        }
    };

    const showOptions = form.values.type === 'select' || form.values.type === 'multi_select';

    return (
        <Modal heading={t`Create Attribute Definition`} onClose={onClose} opened>
            <TextInput
                label={t`Label`}
                placeholder={t`e.g., Role`}
                required
                {...form.getInputProps('label')}
                mb="sm"
            />
            <TextInput
                label={t`Name`}
                placeholder={t`e.g., role (machine name)`}
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
                <Button variant="default" onClick={onClose} disabled={createMutation.isPending}>
                    {t`Cancel`}
                </Button>
                <Button loading={createMutation.isPending} onClick={handleSubmit}>
                    {createMutation.isPending ? t`Working...` : t`Create Attribute`}
                </Button>
            </Group>
        </Modal>
    );
};
