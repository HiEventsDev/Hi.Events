import {t} from "@lingui/macro";
import {Alert, Button, Group, Modal, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconInfoCircle} from "@tabler/icons-react";
import {Order} from "../../../../../types";
import classes from "./EditOrderModal.module.scss";
import {InputGroup} from "../../../../common/InputGroup";

interface EditOrderModalProps {
    opened: boolean;
    onClose: () => void;
    order: Order;
    onSuccess: (values: any) => void;
}

export const EditOrderModal = ({
                                   opened,
                                   onClose,
                                   order,
                                   onSuccess,
                               }: EditOrderModalProps) => {
    const form = useForm({
        initialValues: {
            first_name: order.first_name,
            last_name: order.last_name,
            email: order.email,
        },
        validate: {
            first_name: (value) => !value ? t`First name is required` : null,
            last_name: (value) => !value ? t`Last name is required` : null,
            email: (value) => {
                if (!value) return t`Email is required`;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return t`Invalid email`;
                return null;
            },
        },
    });

    const emailChanged = form.values.email !== order.email;

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            title={t`Edit Order Details`}
            size="md"
            className={classes.modal}
        >
            <form onSubmit={form.onSubmit(onSuccess)}>
                <div>
                    <InputGroup>
                        <TextInput
                            label={t`First Name`}
                            placeholder={t`Enter first name`}
                            required
                            {...form.getInputProps('first_name')}
                        />

                        <TextInput
                            label={t`Last Name`}
                            placeholder={t`Enter last name`}
                            required
                            {...form.getInputProps('last_name')}
                        />
                    </InputGroup>

                    <TextInput
                        label={t`Email`}
                        placeholder={t`Enter email`}
                        required
                        type="email"
                        {...form.getInputProps('email')}
                    />

                    {emailChanged && (
                        <Alert mb={20} icon={<IconInfoCircle size={16}/>} color="orange">
                            <Text size="sm" fw={500}>
                                {t`Important: Changing your email address will update the link to access this order. You will be redirected to the new order link after saving.`}
                            </Text>
                        </Alert>
                    )}

                    <Group justify="flex-end" gap="sm" mt="md">
                        <Button variant="default" onClick={onClose}>
                            {t`Cancel`}
                        </Button>
                        <Button type="submit">
                            {t`Save`}
                        </Button>
                    </Group>
                </div>
            </form>
        </Modal>
    );
};
